# Prompt para o Codex do Futuro

Contexto rapido:
- Projeto: `cartazrapidooficial`
- Stack: PHP + MySQL (Docker Compose)
- Banco principal: `cartaz` (container `cartaz_db`)
- Este repositório guarda tudo, inclusive backup SQL e arquivos sensiveis por decisao do projeto.

Quando for continuar o trabalho, siga esta ordem:
1. Rode `git status` e veja se ha mudancas locais antes de qualquer coisa.
2. Valide containers com `docker ps` e, se necessario, suba com `docker compose up -d`.
3. Se mexer no banco, gere novo dump em `backups/` antes de alterar estrutura.
4. So depois faca alteracoes de codigo e finalize com commit claro e push.

Objetivo permanente:
- Manter o projeto funcionando em producao sem perder dados.
