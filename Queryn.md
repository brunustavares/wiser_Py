<p align="center">
  <img src="static/img/Queryn.jpg" alt="Queryn Logo" width="300">
</p>

# Queryn Features and Architecture

Queryn is an advanced data retrieval engine designed to bridge the gap between natural language user requests and the underlying MySQL database (specifically for the WISEflow/Moodle context). It utilizes a Retrieval-Augmented Generation (RAG) architecture to safely generate SQL, execute it, and summarize the results in plain language.

## Core Features

### 1. Natural Language to SQL Transformation
Queryn allows users to query the database using conversational language (e.g., "Show me the students who submitted assessments last week"). It translates these intents into precise, executable MySQL `SELECT` statements.

### 2. Retrieval-Augmented Generation (RAG)
To handle large database schemas efficiently, Queryn employs a RAG approach:
*   **Schema Chunking**: The database schema (`wiseflow.json`) is split into manageable chunks (one per table).
*   **Vector Indexing**: These chunks are indexed in memory using embeddings (currently implemented with a fallback hashing mechanism).
*   **Context Retrieval**: When a query is received, the system calculates the cosine similarity between the query and schema chunks to retrieve only the most relevant tables, optimizing the LLM's context window.

### 3. Semantic Enrichment Layer
Queryn goes beyond raw table structures by utilizing a semantic descriptor file (`static/DB/wiseflow_semantics.json`). This layer provides the LLM with business logic and context:
*   **Concept Aliases**: Maps business terms to specific database fields.
*   **Table Descriptors**: Provides descriptions, identifies primary keys, and flags specific field types (identity, time).
*   **View Descriptors**: Explains the purpose of database views.
*   **Join Hints**: Explicitly defines how specific tables should be joined to ensure accurate relationship mapping.

### 4. Safety and Security Mechanisms
Queryn operates with a "Safety First" architecture:
*   **Deterministic System Prompt**: The system prompt (`static/DB/system_prompt_generator.md`) strictly instructs the AI to act as a read-only generator.
*   **Keyword Blocklist**: A post-generation validation layer (`is_query_safe`) scans the generated SQL. It automatically blocks execution if destructive keywords are detected (e.g., `DROP`, `DELETE`, `UPDATE`, `INSERT`, `ALTER`, `GRANT`, `EXECUTE`).

### 5. Natural Language Summarization
After executing the SQL query, Queryn performs a second pass with the LLM to interpret the results:
*   **Contextual Summary**: It combines the user's original question, the executed SQL, and the raw JSON results.
*   **Human-Readable Output**: Generates a professional, friendly summary of the data, handling cases where no data is found gracefully.

### 6. LLM Integration
The system is designed to be model-agnostic, with current support configured for:
*   **IAedu/FCT**: Supports token streaming for real-time feedback.
*   **Architecture for Expansion**: Includes logic structures for Ollama and OpenAI integration.

## Underlying Logic & Configuration

### System Prompt (`static/DB/system_prompt_generator.md`)
This file acts as the "brain" instructions for the SQL generation phase. It defines the persona constraints:
*   **Role**: Deterministic MySQL generator.
*   **Mode**: Strict Read-Only.
*   **Output**: Pure SQL without markdown formatting.

### Semantic Configuration (`static/DB/wiseflow_semantics.json`)
This JSON file acts as the "knowledge base" for the database structure, allowing the AI to understand *why* tables exist and *how* they relate, rather than just *what* columns they contain.

### Execution Flow
1.  **Index Check**: Checks if the schema file has changed; rebuilds in-memory index if necessary.
2.  **Retrieval**: Finds relevant schema parts based on the user question.
3.  **Prompt Assembly**: Combines System Prompt + Relevant Schema + Semantic Context + User Question.
4.  **Generation**: Calls the LLM API to generate SQL.
5.  **Safety Check**: Validates the SQL against forbidden keywords.
6.  **Execution**: Runs the query against the MySQL database.
7.  **Summarization**: Calls the LLM again to summarize the JSON results into natural language.

## Licenses

**Author**: Bruno Tavares  
**Contact**: [brunustavares@gmail.com](mailto:brunustavares@gmail.com)  
**LinkedIn**: [https://www.linkedin.com/in/brunomastavares/](https://www.linkedin.com/in/brunomastavares/)  
**Copyright**: 2026-present Bruno Tavares  
**License**: GNU GPL v3 or later  

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.

### Assets

- **Source code**: GNU GPL v3 or later (© Bruno Tavares)  
- **Image**: created using [Image Creator from ©Microsoft Designer](https://www.bing.com/images/create?FORM=IRPGEN)
