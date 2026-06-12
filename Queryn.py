#
# wiser.Py
# Python app for integrated data and indicators management
# related to the tests carried out in WISEflow and Moodle.
# (developed for UAb - Universidade Aberta)
#
# @package    wiser.Py
# @category   app
# @author     Bruno Tavares <brunustavares@gmail.com>
# @link       https://www.linkedin.com/in/brunomastavares/
# @copyright  Copyright (C) 2024-present Bruno Tavares
# @license    GNU General Public License v3 or later
#             https://www.gnu.org/licenses/gpl-3.0.html
# @version    2026061201
# @date       2026-02-25
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.
#

import sys
import os
from unittest import result
import mysql.connector
import requests
import json
from openai import OpenAI
from typing import List, Dict, Any, Tuple
import random
import string
import math
import re

# ficheiros de configuração
auth_lib_bdint = os.path.join(
    os.path.dirname(os.path.abspath(__file__)), 'wiseflow', 'auth_lib_bdint.php'
)
SCHEMA_JSON_PATH = os.path.join(
    os.path.dirname(os.path.abspath(__file__)), "static", "DB", "wiseflow.json"
)
SEMANTIC_DESCRIPTORS_PATH = os.path.join(
    os.path.dirname(os.path.abspath(__file__)), "static", "DB", "wiseflow_semantics.json"
)
SYSTEM_PROMPT_PATH = os.path.join(
    os.path.dirname(os.path.abspath(__file__)), "static", "DB", "system_prompt_generator.md"
)

# obter parâmetros de configuração a partir do ficheiro
with open(auth_lib_bdint, "r") as wf_cfg_file:
    wf_cfg = wf_cfg_file.readlines()
    wf_cfg_file.close()

    # carregar variáveis
    for row in wf_cfg:
        if "//" not in row[:5]:
            if "$host =" in row:
                dbhost = row[row.find("'") + 1:row.rfind("'")]

            elif "$port =" in row:
                dbport = row[row.find("'") + 1:row.rfind("'")]

            elif "$usr  =" in row:
                dbuser = row[row.find("'") + 1:row.rfind("'")]

            elif "$pwd  =" in row:
                dbpass = row[row.find("'") + 1:row.rfind("'")]

            elif "$db   =" in row:
                dbname = row[row.find("'") + 1:row.rfind("'")]

MYSQL_CONFIG = {
    "host": dbhost,
    "port": int(dbport),
    "user": dbuser,
    "password": dbpass,
    "database": dbname,
}

# índice global em memória do esquema
schema_chunks: List[Dict[str, Any]] = []
schema_signature: Tuple[float, int] = (0.0, 0)


# gerar string aleatória para thread_id
def generate_random_string(length):
    lowercase = string.ascii_lowercase
    uppercase = string.ascii_uppercase
    digits = string.digits
    special = string.punctuation

    required = [
        random.choice(lowercase),
        random.choice(uppercase),
        random.choice(digits),
        random.choice(special),
    ]

    all_chars = lowercase + uppercase + digits + special
    remaining = [random.choice(all_chars) for _ in range(length - len(required))]

    result = required + remaining
    random.shuffle(result)

    return ''.join(result)


# obter configurações do LLM
def get_ai_settings() -> Dict[str, Any]:
    conn = mysql.connector.connect(**MYSQL_CONFIG)
    cursor = conn.cursor(dictionary=True)

    cursor.execute(
        """
        SELECT provider, endpoint, agent_id, api_key, channel_id, model_name
        FROM llm_settings
        WHERE idx = 1
        """
    )
    main = cursor.fetchone()

    cursor.execute(
        """
        SELECT provider, endpoint, agent_id, api_key, channel_id, model_name
        FROM llm_settings
        WHERE idx = 2
        """
    )
    fallback = cursor.fetchone()


    cursor.close()
    conn.close()

    if not main:
        raise RuntimeError("não foram encontradas configurações de AI")

    return main, fallback


# LLM: IAedu/FCT (chat-style, com streaming de tokens)
def llm_generate_iaedu(prompt: str, endpoint: str, agent_id: str, api_key: str, channel_id: str) -> str:
    endpoint = f"{endpoint}/agent/{agent_id}/stream"

    headers = {"x-api-key": api_key}

    payload = {
               "channel_id": channel_id,
               "thread_id": generate_random_string(21),
               "user_info": "{}",
               "message": prompt
              }

    response = requests.post(
        endpoint,
        headers=headers,
        data=payload,
        timeout=300,
        stream=True
    )

    response.raise_for_status()

    full_sql_from_tokens = ""

    for raw_line in response.iter_lines():
        if not raw_line:
            continue

        try:
            line = raw_line.decode("utf-8").strip()

            if line.startswith("data: "):
                line = line[6:]

            obj = json.loads(line)

        except:
            continue

        if obj.get("type") == "error":
            error_msg = obj.get("content", "")

            print(f"\n{obj}\n")

            return f"erro IAedu/FCT: {error_msg}"

        if obj.get("type") == "token":
            full_sql_from_tokens += obj.get("content", "")

        if obj.get("type") == "message":
            content = obj.get("content")

            if isinstance(content, dict):
                final_sql = content.get("content", "")

                if final_sql:
                    return final_sql.strip()
                
            elif isinstance(content, str) and content:
                return content.strip()
            
    # fallback: se nenhuma mensagem foi enviada, retorna os tokens obtidos
    return full_sql_from_tokens.strip()


# LLM: IAedu/FCT (não permite embeddings → usar um fallback em Python puro)
def embed_fallback(text: str) -> List[float]:
    import hashlib

    h = hashlib.sha256(text.encode()).digest()

    return [b / 255 for b in h[:128]]  # dummy embedding


# TODO: LLM: Ollama (chat-style)
def llm_generate_ollama(prompt: str, model_name: str) -> str:
    endpoint = f"http://localhost:11434/api/chat"

    payload = {
               "model": model_name,
               "messages": [
                            {"role": "system", "content": prompt},
                           ],
               "stream": False,
              }

    response = requests.post(
        endpoint,
        json=payload,
        timeout=300,
    )

    response.raise_for_status()

    data = response.json()

    return data["message"]["content"]


# LLM: OpenAI (chat)
def llm_generate_openai(prompt: str, endpoint: str, api_key: str, model_name: str) -> str:
    client = OpenAI(
        api_key=api_key,
        base_url=endpoint
    )

    resp = client.chat.completions.create(
        model=model_name,
        messages=[
            {"role": "user", "content": prompt}
        ],
        temperature=0.1,
    )

    return resp.choices[0].message.content


# carregamento de ficheiros esquemáticos da base de dados (como JSON, para uso interno)
def _load_json_file(path: str) -> Any:
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)


# carregamento de ficheiros esquemáticos da base de dados (como texto puro, para passar ao LLM)
def _load_json_text(path: str) -> str:
    with open(path, "r", encoding="utf-8") as f:
        return f.read().strip()


# carregamento do esquema JSON (para uso interno)
def get_schema_json() -> Any:
    return _load_json_file(SCHEMA_JSON_PATH)


def _looks_like_table_metadata(value: Any) -> bool:
    if not isinstance(value, dict):
        return False

    table_keys = {
        "columns",
        "indexes",
        "relations",
        "relationships",
        "entity",
        "description",
        "business_rules",
    }

    return bool(table_keys.intersection(value.keys()))


def _iter_database_tables(payload: Any) -> List[Dict[str, Any]]:
    tables = []

    def add_table(database_name: str, table_name: str, table_meta: Any):
        if not isinstance(table_name, str) or not _looks_like_table_metadata(table_meta):
            return

        qualified_table_name = (
            f"{database_name}.{table_name}" if database_name else table_name
        )

        tables.append(
            {
                "database_name": database_name,
                "table_name": table_name,
                "qualified_table_name": qualified_table_name,
                "meta": table_meta,
            }
        )

    def add_tables_from_mapping(database_name: str, table_mapping: Any):
        if not isinstance(table_mapping, dict):
            return

        for table_name, table_meta in table_mapping.items():
            add_table(database_name, table_name, table_meta)

    if isinstance(payload, dict):
        databases = payload.get("databases")

        if isinstance(databases, list):
            for database_payload in databases:
                if not isinstance(database_payload, dict):
                    continue

                database_name = (
                    database_payload.get("database")
                    or database_payload.get("name")
                    or database_payload.get("schema")
                    or ""
                )
                add_tables_from_mapping(database_name, database_payload.get("tables", {}))

            return tables

        if isinstance(databases, dict):
            for database_name, database_payload in databases.items():
                if not isinstance(database_payload, dict):
                    continue

                add_tables_from_mapping(
                    database_name,
                    database_payload.get("tables", database_payload)
                )

            return tables

        if isinstance(payload.get("tables"), dict):
            add_tables_from_mapping("", payload["tables"])

            return tables

        add_tables_from_mapping("", payload)

    return tables


def _table_ref_from_field(field_ref: str) -> str:
    parts = [part for part in field_ref.split(".") if part]

    if len(parts) >= 3:
        return ".".join(parts[:-1])

    if len(parts) == 2:
        return parts[0]

    return ""


# assinatura do ficheiro de esquema (timestamp + tamanho) para detectar mudanças e atualizar o índice
def _schema_file_signature() -> Tuple[float, int]:
    stat = os.stat(SCHEMA_JSON_PATH)

    return (stat.st_mtime, stat.st_size)


# carregamento dos descritores semânticos (conceitos, tabelas, vistas, etc)
def load_semantic_descriptors() -> Dict[str, Any]:
    try:
        return _load_json_file(SEMANTIC_DESCRIPTORS_PATH)
    
    except FileNotFoundError:
        return {}
    
    except json.JSONDecodeError:
        return {}
    

# carregamento da prompt de sistema (instruções específicas para o LLM, como regras de formatação, restrições, etc)
def load_system_prompt() -> str:
    with open(SYSTEM_PROMPT_PATH, "r", encoding="utf-8") as f:
        return f.read()


# construção do contexto semântico a partir dos descritores (para passar ao LLM)
def build_semantic_context(
    semantic: Dict[str, Any],
    question: str = "",
    selected_tables: List[str] = None
) -> Tuple[str, str]:
    if not semantic:
        return "", ""

    if selected_tables is None:
        selected_tables = []

    selected_names = set()
    selected_tokens = set()

    for selected in selected_tables:
        if not selected:
            continue

        selected_lower = selected.lower()
        selected_simple = selected_lower.split(".")[-1]

        selected_names.add(selected_lower)
        selected_names.add(selected_simple)
        selected_tokens.update(_tokenize_text(selected_simple))

    question_tokens = set(_tokenize_text(question))

    def _content_tokens(obj: Any) -> set:
        return set(_tokenize_text(json.dumps(obj, ensure_ascii=False)))

    def _matches_selected_table_ref(table_ref: str) -> bool:
        if not selected_names:
            return True

        table_ref_lower = table_ref.lower()
        table_ref_simple = table_ref_lower.split(".")[-1]

        return table_ref_lower in selected_names or table_ref_simple in selected_names

    def _is_relevant(name: str, payload: Any) -> bool:
        pool = set(_tokenize_text(name)).union(_content_tokens(payload))
        name_lower = name.lower()
        name_simple = name_lower.split(".")[-1]

        if selected_names and {name_lower, name_simple}.intersection(selected_names):
            return True

        if selected_tokens and selected_tokens.intersection(pool):
            return True

        if question_tokens and question_tokens.intersection(pool):
            return True

        return False

    sections = []
    join_lines = []

    # Detectar se o dicionário semântico está no formato novo (base-de-dados/tabela-céntrico)
    semantic_tables = _iter_database_tables(semantic)
    is_new_format = bool(semantic_tables)

    if is_new_format:
        for table_entry in semantic_tables:
            table_name = table_entry["qualified_table_name"]
            meta = table_entry["meta"]

            if not _is_relevant(table_name, meta):
                continue

            parts = [f"Table: {table_name}"]
            if meta.get("entity"):
                parts.append(f"Entity: {meta['entity']}")
            if meta.get("description"):
                parts.append(f"Description: {meta['description']}")

            columns = meta.get("columns", {})
            if columns:
                col_lines = []
                for col_name, col_meta in columns.items():
                    col_parts = [col_name]
                    details = []
                    if col_meta.get("role"):
                        details.append(f"role={col_meta['role']}")
                    if col_meta.get("type"):
                        details.append(f"type={col_meta['type']}")
                    if col_meta.get("description"):
                        details.append(f"description={col_meta['description']}")
                    if details:
                        col_parts.append(f"({'; '.join(details)})")
                    col_lines.append(" - " + " ".join(col_parts))
                parts.append("Columns:\n" + "\n".join(col_lines))

            business_rules = meta.get("business_rules", [])
            if business_rules:
                parts.append("Business Rules:\n" + "\n".join(f"- {rule}" for rule in business_rules))

            sections.append("\n".join(parts))

        for table_entry in semantic_tables:
            table_name = table_entry["qualified_table_name"]
            meta = table_entry["meta"]

            if not _is_relevant(table_name, meta):
                continue

            relationships = meta.get("relationships", [])
            for rel in relationships:
                from_field = rel.get("from", "")
                to_field = rel.get("to", "")
                if not from_field or not to_field:
                    continue

                from_table = _table_ref_from_field(from_field)
                to_table = _table_ref_from_field(to_field)
                condition = f"{from_field} = {to_field}"

                if selected_names:
                    if (
                        _matches_selected_table_ref(from_table)
                        or _matches_selected_table_ref(to_table)
                    ):
                        join_lines.append(condition)
                else:
                    join_lines.append(condition)
    else:
        # Formato legado
        aliases = semantic.get("concept_aliases", {})

        if aliases:
            lines = []

            for key, vals in aliases.items():
                alias_tokens = set(_tokenize_text(key))

                for alias in vals:
                    alias_tokens.update(_tokenize_text(alias))

                if not question_tokens or question_tokens.intersection(alias_tokens):
                    lines.append(f"{key}: {', '.join(vals)}")

            if lines:
                sections.append("CONCEPT ALIASES:\n" + "\n".join(lines))

        semantic_layer = semantic.get("semantic_layer", {})

        if semantic_layer:
            lines = []

            for name, meta in semantic_layer.items():
                if not _is_relevant(name, meta):
                    continue

                parts = [f"table={name}"]

                if meta.get("purpose"):
                    parts.append(f"purpose={meta['purpose']}")

                if meta.get("prefer_for"):
                    parts.append(f"prefer_for={', '.join(meta['prefer_for'])}")

                if meta.get("key_columns"):
                    parts.append(f"key_columns={', '.join(meta['key_columns'])}")

                if meta.get("important_filters"):
                    parts.append(f"important_filters={', '.join(meta['important_filters'])}")

                if meta.get("date_columns"):
                    parts.append(f"date_columns={', '.join(meta['date_columns'])}")

                lines.append("; ".join(parts))

            if lines:
                sections.append("SEMANTIC LAYER:\n" + "\n".join(lines))

        patterns = semantic.get("common_query_patterns", {})

        if patterns:
            lines = []

            for name, meta in patterns.items():
                if not _is_relevant(name, meta):
                    continue

                parts = [f"pattern={name}"]

                if meta.get("tables"):
                    parts.append(f"tables={', '.join(meta['tables'])}")

                if meta.get("views"):
                    parts.append(f"views={', '.join(meta['views'])}")

                if meta.get("filter_fields"):
                    parts.append(f"filter_fields={', '.join(meta['filter_fields'])}")

                if meta.get("aggregations"):
                    parts.append(f"aggregations={', '.join(meta['aggregations'])}")

                lines.append("; ".join(parts))

            if lines:
                sections.append("COMMON QUERY PATTERNS:\n" + "\n".join(lines))

        business_rules = semantic.get("business_rules", {})

        if business_rules:
            lines = []

            for key, value in business_rules.items():
                rule_tokens = set(_tokenize_text(key))
                rule_tokens.update(_tokenize_text(value))

                if not question_tokens or question_tokens.intersection(rule_tokens):
                    lines.append(f"{key}: {value}")

            if lines:
                sections.append("BUSINESS RULES:\n" + "\n".join(lines))

        for table_name, meta in semantic_layer.items():
            if not _is_relevant(table_name, meta):
                continue

            joins = meta.get("joins", [])

            for join in joins:
                condition = join.get("condition", "")
                target = join.get("table", "")

                if not condition:
                    continue

                if selected_names:
                    if (
                        _matches_selected_table_ref(table_name)
                        or _matches_selected_table_ref(target)
                    ):
                        join_lines.append(condition)
                else:
                    join_lines.append(condition)

        for pattern_name, meta in patterns.items():
            if not _is_relevant(pattern_name, meta):
                continue

            for condition in meta.get("join_path", []):
                join_lines.append(condition)

    if join_lines:
        join_lines = list(dict.fromkeys(join_lines))

    join_section = ""

    if join_lines:
        join_section = "JOIN HINTS:\n" + "\n".join(join_lines)

    semantic_context = "\n\n".join(sections)

    return semantic_context, join_section


# dividir o esquema em chunks/pedaços (um por tabela)
def chunk_schema(schema: Any) -> List[Dict[str, Any]]:
    chunks = []

    table_entries = _iter_database_tables(schema)

    if table_entries:
        for table_entry in table_entries:
            database_name = table_entry["database_name"]
            table_name = table_entry["table_name"]
            qualified_table_name = table_entry["qualified_table_name"]
            table_meta = table_entry["meta"]

            definition = {
                "table": table_name,
                "definition": table_meta,
            }

            if database_name:
                definition["database"] = database_name
                definition["qualified_table"] = qualified_table_name

            text = json.dumps(
                definition,
                indent=2,
                ensure_ascii=False
            )

            chunks.append(
                {
                    "id": f"schema_{qualified_table_name.replace('.', '_')}",
                    "database_name": database_name,
                    "table_name": qualified_table_name,
                    "physical_table_name": table_name,
                    "text": text,
                }
            )

        return chunks

    # Se schema for um dicionário sem metadados reconhecidos, tentar o formato antigo
    if isinstance(schema, dict):
        tables_dict = schema.get("tables", schema)
        for table_name, table_meta in tables_dict.items():
            if not isinstance(table_meta, dict):
                continue

            text = json.dumps(
                {"table": table_name, "definition": table_meta},
                indent=2,
                ensure_ascii=False
            )

            chunks.append(
                {
                    "id": f"schema_{table_name}",
                    "table_name": table_name,
                    "text": text,
                }
            )
        return chunks

    # Se schema for uma lista (formato legado)
    if isinstance(schema, list):
        for entry in schema:
            if not isinstance(entry, dict):
                continue

            table_section = entry.get("table")

            # formato principal: {"table": {"courses": {...}, "flows": {...}}}
            if isinstance(table_section, dict):
                for table_name, table_meta in table_section.items():
                    if not isinstance(table_meta, dict):
                        continue

                    text = json.dumps(
                        {"table": table_name, "definition": table_meta},
                        indent=2,
                        ensure_ascii=False
                    )

                    chunks.append(
                        {
                            "id": f"schema_{table_name}",
                            "table_name": table_name,
                            "text": text,
                        }
                    )

            # fallback legado: {"table": "table_name", ...}
            elif isinstance(table_section, str):
                table_name = table_section
                table_meta = {k: v for k, v in entry.items() if k != "table"}

                text = json.dumps(
                    {"table": table_name, "definition": table_meta},
                    indent=2,
                    ensure_ascii=False
                )

                chunks.append(
                    {
                        "id": f"schema_{table_name}",
                        "table_name": table_name,
                        "text": text,
                    }
                )

    return chunks


def _tokenize_text(value: str) -> List[str]:
    return re.findall(r"[a-zA-Z0-9_]+", value.lower())


def _extract_schema_keywords(table_name: str, table_text: str) -> set:
    keywords = set(_tokenize_text(table_name))
    keywords.update(_tokenize_text(table_text))

    return keywords


# construção / atualização do índice do esquema
def build_schema_index():
    global schema_chunks, schema_signature

    print("\na carregar o esquema da base de dados...")

    schema = get_schema_json()
    chunks = chunk_schema(schema)

    print("a indexar o esquema em memória...")

    schema_chunks = []

    for chunk in chunks:
        emb = embed_fallback(chunk["text"])
        keywords = _extract_schema_keywords(chunk.get("table_name", ""), chunk["text"])

        schema_chunks.append(
            {
                "id": chunk["id"],
                "database_name": chunk.get("database_name", ""),
                "table_name": chunk.get("table_name", ""),
                "physical_table_name": chunk.get("physical_table_name", ""),
                "text": chunk["text"],
                "embedding": emb,
                "keywords": keywords,
            }
        )

    schema_signature = _schema_file_signature()

    print(f"esquema carregado com {len(schema_chunks)} chunks")


# guarda de segurança para queries, bloqueando operações destrutivas
def is_query_safe(sql: str) -> Tuple[bool, str]:
    sql_lower = sql.lower()

    forbidden = [
                 "create ",
                 "alter ",
                 "truncate ",
                 "drop ",
                 "insert ",
                 "update ",
                 "set",
                 "delete ",
                 "replace ",
                 "grant ",
                 "revoke ",
                 "call ",
                 "execute"
                ]

    for keyword in forbidden:
        if keyword in sql_lower:
            return False, f"query perigosa bloqueada: '{keyword.strip()}'"

    return True, ""


# executa a query obtém os resultados
def execute_sql(sql: str) -> Tuple[List[str], List[Tuple[Any, ...]]]:
    conn = mysql.connector.connect(**MYSQL_CONFIG)
    cursor = conn.cursor()

    try:
        cursor.execute(sql)
        rows = cursor.fetchall()
        column_names = [desc[0] for desc in cursor.description] if cursor.description else []
    except Exception as e:
        rows = [(str(e),)]
        column_names = ["erro_mysql"]

    conn.commit()
    cursor.close()
    conn.close()

    return column_names, rows


# formata os resultados da query como JSON
def format_json(columns: List[str], rows: List[Tuple[Any, ...]]) -> str:
    if not columns:
        return json.dumps({"message": "sem resultados"}, indent=2)

    data = []

    for row in rows:
        obj = {col: row[i] for i, col in enumerate(columns)}

        data.append(obj)

    return json.dumps(data, indent=2, default=str)


# RAG: gerar uma query a partir de um pedido do utilizador
def generate_sql_from_question(user_request: str) -> str:
    global schema_chunks

    print("a carregar as configurações de AI...")

    main, fallback = get_ai_settings()

    def cosine(a, b):
        dot = sum(x * y for x, y in zip(a, b))
        na = math.sqrt(sum(x * x for x in a))
        nb = math.sqrt(sum(x * x for x in b))

        return dot / (na * nb + 1e-8)

    def retrieve_relevant_schema(question: str, top_k: int = 5) -> List[Dict[str, str]]:
        q_emb = embed_fallback(question)
        q_tokens = set(_tokenize_text(question))

        scored = []

        for c in schema_chunks:
            keyword_hits = len(q_tokens.intersection(c.get("keywords", set())))
            lexical_score = keyword_hits / max(len(q_tokens), 1)
            semantic_score = cosine(q_emb, c["embedding"])

            # prioridade ao overlap lexical, com embedding como desempate fraco
            total_score = (lexical_score * 0.9) + (semantic_score * 0.1)
            scored.append((total_score, keyword_hits, c))

        scored.sort(reverse=True, key=lambda x: (x[0], x[1]))

        if not scored:
            return []

        best_hits = max(hit_count for _, hit_count, _ in scored)

        if best_hits > 0:
            filtered = [(score, chunk) for score, hits, chunk in scored if hits > 0]

        else:
            filtered = [(score, chunk) for score, _, chunk in scored]

        limit = max(1, min(top_k, len(filtered)))

        return [
            {
                "table_name": chunk.get("table_name", ""),
                "text": chunk["text"],
            }
            for _, chunk in filtered[:limit]
        ]

    current_sig = _schema_file_signature()

    if not schema_chunks or current_sig != schema_signature:
        print("esquema modificado, a reconstrur índice...")

        build_schema_index()

    print("a obter o esquema relevante...")
    
    retrieved_schema_chunks = retrieve_relevant_schema(user_request)
    selected_table_names = [chunk["table_name"] for chunk in retrieved_schema_chunks if chunk.get("table_name")]
    schema_context = "\n\n".join(chunk["text"] for chunk in retrieved_schema_chunks)

    semantic = load_semantic_descriptors()
    semantic_context, join_hints = build_semantic_context(
        semantic,
        question=user_request,
        selected_tables=selected_table_names
    )
    system_prompt = load_system_prompt()

    prompt = f"""
SYSTEM PROMPT:
You are a deterministic MySQL generator operating in STRICT READ-ONLY MODE.
Your task is to generate exactly ONE safe, executable MySQL SELECT statement based exclusively on the provided schema JSON and semantic descriptors.

{system_prompt}

--------------------------------------
# INPUT DATA
--------------------------------------

SCHEMA:
{schema_context}

SEMANTIC_DESCRIPTORS:
{semantic_context}

{join_hints}

--------------------------------------
# NOW SOLVE THE USER REQUEST
--------------------------------------

User request:
\"\"\"{user_request}\"\"\"

OUTPUT:
Return ONLY the final SELECT statement.
"""
    
    if main["provider"] == "iaedu":
        sql = llm_generate_iaedu(prompt, main["endpoint"], main["agent_id"], main["api_key"], main["channel_id"])

    elif main["provider"] == "ollama":
        sql = llm_generate_ollama(prompt, main["model_name"])
    
    elif main["provider"] == "openai" or main["provider"] == "openrouter":
        sql = llm_generate_openai(prompt, main["endpoint"], main["api_key"], main["model_name"])
    
    if sql.lower().startswith("erro") and fallback:
        print("o LLM principal falhou, a tentar secundário...")

        sql = llm_generate_openai(prompt, fallback["endpoint"], fallback["api_key"], fallback["model_name"])

    # Limpar a query SQL (remover blocos de markdown e espaços desnecessários)
    if sql:
        sql = sql.strip()
        if sql.startswith("```"):
            lines = sql.splitlines()
            if lines[0].startswith("```"):
                lines = lines[1:]
            if lines and lines[-1].strip() == "```":
                lines = lines[:-1]
            sql = "\n".join(lines).strip()
        if sql.lower().startswith("sql"):
            sql = sql[3:].strip()
        sql = sql.strip()

    print("\nquery gerada:")
    print(sql)

    return sql


# RAG: gerar um resumo em linguagem natural, a partir dos resultados da query e do pedido original
def generate_summary(user_request: str, sql_query: str, json_data: str) -> str:
    main, fallback = get_ai_settings()
    
    # construir a prompt de interpretação
    summary_prompt = f"""
You are a helpful data assistant. 
The user asked: "{user_request}"
To answer this, the following SQL was executed: {sql_query}
    
The database returned the following JSON data:
{json_data}
    
INSTRUCTIONS:
1. Provide a clear, concise natural language answer based ONLY on the data provided.
2. Use a professional and friendly tone.
3. Do NOT make any assumptions beyond the provided data. If the data does not contain the answer, say you don't know.
4. If the data is empty, politely inform the user that no records were found.
5. The default output is plain text in natural language.
6. If the user explicitly requests a table OR the data contains multiple rows/columns, return an HTML table using this template (mandatory):

<table class="table table-striped table-hover table-borderless results-table" id="full_report_table">
<thead class="table-primary results-table-header">
<tr>
<th>Column Name</th>
</tr>
</thead>
<tbody>
<tr>
<td>Value</td>
</tr>
</tbody>
</table>

7. Include a brief natural language explanation before the table.
8. Always include <thead> and <tbody>.
9. Never include Markdown tables or inline CSS.
10. For aggregated or grouped queries, include descriptive column headers.
11. If metadata about the SQL result is provided, use it to ensure proper column headers and table structure.
12. If the result is a single value or scalar, return only natural language text.
13. If the result returns an error, return the full error message in a natural language explanation.
"""

    if main["provider"] == "iaedu":
        summary = llm_generate_iaedu(summary_prompt, main["endpoint"], main["agent_id"], main["api_key"], main["channel_id"])

    elif main["provider"] == "ollama":
        summary = llm_generate_ollama(summary_prompt, main["model_name"])
    
    elif main["provider"] == "openai" or main["provider"] == "openrouter":
        summary = llm_generate_openai(summary_prompt, main["endpoint"], main["api_key"], main["model_name"])
    
    if summary.lower().startswith("erro") and fallback:
        print("o LLM principal falhou, a tentar secundário...")

        summary = llm_generate_openai(summary_prompt, fallback["endpoint"], fallback["api_key"], fallback["model_name"])

    return summary.strip()


# fazer pergunta, executar query, formatar resultados
def answer_question(user_request: str) -> Dict[str, str]:
    sql = generate_sql_from_question(user_request)

    # lidar com erros do LLM (se a resposta começar com "erro", a informação é retornada ao utilizador de forma amigável)
    if sql.lower().startswith("erro"):
        json_out = json.dumps({"erro": sql}, indent=2)

        sql = ""

    else:
        # verificação de segurança da query antes de executar
        is_safe, reason = is_query_safe(sql)

        if not is_safe:
            json_out = json.dumps({"erro": reason, "sql": sql}, indent=2)

        else:    
            columns, rows = execute_sql(sql)

            json_out = format_json(columns, rows)

    natural_answer = generate_summary(user_request, sql, json_out)

    print("\nresultados em JSON:")
    print(json_out)

    return {
        "sql": sql,
        "json": json_out,
        "answer": natural_answer
    }


# função principal para interagir com a Queryn (pode ser chamada por uma interface web, CLI, etc)
def prompt(question: str):
    build_schema_index()

    return answer_question(question)


if __name__ == "__main__":
    result = prompt(input("\npedido: "))

    print("\nresposta natural:")
    print(result["answer"])
