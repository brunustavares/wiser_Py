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
# @version    2026022508
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
import mysql.connector
import requests
import openai
import json
from typing import List, Dict, Any, Tuple
import random
import string
import math

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

# TODO: selector de LLM
AI_SETTINGS_ID = 1

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
        SELECT llm_provider, agent_id, api_key, channel_id, model_name
        FROM ai_settings
        WHERE id = %s
        """,
        (AI_SETTINGS_ID,),
    )
    row = cursor.fetchone()
    cursor.close()
    conn.close()

    if not row:
        raise RuntimeError("não foram encontradas configurações de AI")

    return row


# LLM: IAedu/FCT (chat-style, com streaming de tokens)
def llm_generate_iaedu(prompt: str, agent_id: str, api_key: str, channel_id: str) -> str:
    endpoint = f"https://api.iaedu.pt/agent-chat/api/v1/agent/{agent_id}/stream"

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


# TODO: LLM: OpenAI (chat)
def llm_generate_openai(prompt: str, model_name: str) -> str:
    resp = openai.ChatCompletion.create(
        model = model_name,
        messages=[
                  {"role": "system", "content": prompt},
                 ],
        temperature=0.1,
    )

    return resp["choices"][0]["message"]["content"]


# carregamento de ficheiros esquemáticos da base de dados (como JSON, para uso interno)
def _load_json_file(path: str) -> Any:
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)


# carregamento de ficheiros esquemáticos da base de dados (como texto puro, para passar ao LLM)
def _load_json_text(path: str) -> str:
    with open(path, "r", encoding="utf-8") as f:
        return f.read().strip()


# carregamento do esquema JSON (para uso interno)
def get_schema_json() -> List[Dict[str, Any]]:
    return _load_json_file(SCHEMA_JSON_PATH)


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
def build_semantic_context(semantic: Dict[str, Any]) -> Tuple[str, str]:
    if not semantic:
        return "", ""

    sections = []

    aliases = semantic.get("concept_aliases", {})

    if aliases:
        lines = []

        for key, vals in aliases.items():
            lines.append(f"{key}: {', '.join(vals)}")

        sections.append("CONCEPT ALIASES:\n" + "\n".join(lines))

    # tabelas e metadados relevantes (chaves primárias, campos de identidade, campos de tempo, descrições)
    tables = semantic.get("tables", {})

    if tables:
        lines = []

        for name, meta in tables.items():
            parts = [f"table={name}"]

            if meta.get("description"):
                parts.append(f"description={meta['description']}")

            if meta.get("primary_key"):
                parts.append(f"primary_key={', '.join(meta['primary_key'])}")

            if meta.get("identity_fields"):
                parts.append(f"identity_fields={', '.join(meta['identity_fields'])}")

            if meta.get("time_fields"):
                parts.append(f"time_fields={', '.join(meta['time_fields'])}")

            lines.append("; ".join(parts))

        sections.append("TABLE DESCRIPTORS:\n" + "\n".join(lines))

    # vistas e respectivas descrições
    views = semantic.get("views", {})

    if views:
        lines = []

        for name, desc in views.items():
            lines.append(f"view={name}; description={desc}")

        sections.append("VIEW DESCRIPTORS:\n" + "\n".join(lines))

    # relações entre tabelas
    join_lines = []

    for table_name, meta in tables.items():
        joins = meta.get("joins", {})

        for target, condition in joins.items():
            join_lines.append(condition)

    join_section = ""

    if join_lines:
        join_section = "JOIN HINTS:\n" + "\n".join(join_lines)

    semantic_context = "\n\n".join(sections)

    return semantic_context, join_section


# dividir o esquema em chunks/pedaços (um por tabela)
def chunk_schema(schema: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    chunks = []

    for table in schema:
        table_name = table["table"]

        text = json.dumps(table, indent=2)

        chunks.append(
            {
                "id": f"schema_{table_name}",
                "text": text,
            }
        )

    return chunks


# construção / atualização do índice do esquema
def build_schema_index():
    global schema_chunks, schema_signature

    print("a carregar as configurações de AI...")

    settings = get_ai_settings()
    provider = settings["llm_provider"]
    api_key = settings["api_key"]

    if provider == "openai":
        openai.api_key = api_key

    print("a carregar o esquema da base de dados...")

    schema = get_schema_json()
    chunks = chunk_schema(schema)

    print("a indexar o esquema em memória...")

    schema_chunks = []

    for chunk in chunks:
        emb = embed_fallback(chunk["text"])

        schema_chunks.append(
            {
                "id": chunk["id"],
                "text": chunk["text"],
                "embedding": emb,
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
    cursor.execute(sql)

    try:
        rows = cursor.fetchall()

        column_names = [desc[0] for desc in cursor.description] if cursor.description else []

    except mysql.connector.errors.InterfaceError:
        rows = []
        column_names = []

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

    settings = get_ai_settings()
    provider = settings["llm_provider"]
    agent_id = settings["agent_id"]
    api_key = settings["api_key"]
    channel_id = settings["channel_id"]
    model_name = settings["model_name"]

    if provider == "openai":
        openai.api_key = api_key

    def cosine(a, b):
        dot = sum(x * y for x, y in zip(a, b))
        na = math.sqrt(sum(x * x for x in a))
        nb = math.sqrt(sum(x * x for x in b))

        return dot / (na * nb + 1e-8)

    def retrieve_relevant_schema(question: str, top_k: int = 5) -> List[str]:
        q_emb = embed_fallback(question)

        scored = [
            (cosine(q_emb, c["embedding"]), c["text"])
            for c in schema_chunks
        ]
        scored.sort(reverse=True, key=lambda x: x[0])

        return [t for _, t in scored[:top_k]]

    current_sig = _schema_file_signature()

    if not schema_chunks or current_sig != schema_signature:
        print("esquema modificado, a reconstrur índice...")

        build_schema_index()

    print("a obter o esquema relevante...")
    
    retrieved_schema_chunks = retrieve_relevant_schema(user_request)
    schema_context = "\n\n".join(retrieved_schema_chunks)
    schema_json_text = _load_json_text(SCHEMA_JSON_PATH)

    semantic = load_semantic_descriptors()
    semantic_context, join_hints = build_semantic_context(semantic)
    semantic_json_text = _load_json_text(SEMANTIC_DESCRIPTORS_PATH) if semantic else ""
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

{schema_json_text}

SEMANTIC_DESCRIPTORS:
{semantic_context}

{semantic_json_text}

{join_hints}

--------------------------------------
# NOW SOLVE THE USER REQUEST
--------------------------------------

User request:
\"\"\"{user_request}\"\"\"

OUTPUT:
Return ONLY the final SELECT statement.
"""

    sql = llm_generate_iaedu(prompt, agent_id, api_key, channel_id)

    return sql


# RAG: gerar um resumo em linguagem natural, a partir dos resultados da query e do pedido original
def generate_summary(user_request: str, sql_query: str, json_data: str) -> str:
    settings = get_ai_settings()
    provider = settings["llm_provider"]
    
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
3. If the data is empty, politely inform the user that no records were found.
4. Do NOT make any assumptions beyond the provided data. If the data does not contain the answer, say you don't know.
5. Do not wrap HTML in markdown code blocks.
6. Do not use ```html fences.
"""

    if provider == "iaedu":
        return llm_generate_iaedu(summary_prompt, settings["agent_id"], settings["api_key"], settings["channel_id"])
    
    elif provider == "ollama":
        return llm_generate_ollama(summary_prompt, settings["model_name"])
    
    elif provider == "openai":
        return llm_generate_openai(summary_prompt, settings["model_name"])
    
    else:
        return "erro: LLM desconhecido."


# fazer pergunta, executar query, formatar resultados
def answer_question(user_request: str) -> Dict[str, str]:
    sql = generate_sql_from_question(user_request)

    # verificação de segurança da query antes de executar
    is_safe, reason = is_query_safe(sql)

    if not is_safe:
        return {
            "sql": sql,
            "markdown_short": f"⚠️ **query bloqueada por segurança**\n\nmotivo: {reason}",
            "json": json.dumps({"erro": reason, "sql": sql}, indent=2),
            "summary": (
                f"a query criada foi bloqueada por conter uma operação perigosa. "
                f"motivo: {reason}"
            ),
        }

    columns, rows = execute_sql(sql)

    json_out = format_json(columns, rows)

    natural_answer = generate_summary(user_request, sql, json_out)

    return {
        "sql": sql,
        "json": json_out,
        "answer": natural_answer
    }


# função principal para interagir com a AI (pode ser chamada por uma interface web, CLI, etc)
def ask_AI(question: str):
    build_schema_index()

    return answer_question(question)


if __name__ == "__main__":
    result = ask_AI(input("\npedido: "))

    print("\nquery gerada:")
    print(result["sql"])

    print("\nresultados em JSON:")
    print(result["json"])

    print("\nresposta natural:")
    print(result["answer"])
