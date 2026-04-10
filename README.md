# PolyMath® Sistema de Log

**Versão:** 1.1  
**Autor:** Enio Alves Borges  
**GitHub:** [nio00110011](https://github.com/nio00110011)

## Descrição

Plugin WordPress que registra automaticamente:
- IP do visitante
- Geolocalização (cidade/país via ip-api.com)
- Navegador e dispositivo
- Páginas acessadas (incluindo erros 404)
- Tentativas de login inválidas (com username)

A busca avançada permite filtrar logs por qualquer campo, com suporte a múltiplos termos separados por `;` (ex.: `Linux ; 35.242.132.122`).

## Funcionalidades

- ✅ Registro de todos os acessos às páginas (front‑end)
- ✅ Captura de erros 404
- ✅ Tentativas de login com falha
- ✅ Geolocalização por IP (cidade/país)
- ✅ Detecção de navegador e dispositivo
- ✅ Painel administrativo com tabela paginada
- ✅ Busca global por qualquer coluna (parcial, case‑insensitive)
- ✅ Busca múltipla com `;` (AND entre termos)
- ✅ Filtro por tipo de evento (acesso_pagina, erro_404, login_falhou)
- ✅ Limpeza automática de logs antigos (configurável em dias)

## Instalação

Veja o arquivo [INSTALL.md](INSTALL.md) para instruções detalhadas.

## Doações ☕

Se este plugin foi útil para você e quiser contribuir com um café (ou muitos), fique à vontade!

**Chave PIX (copia e cola):**  
`soletrepix@gmail.com` 🐠🐟

**Titular:** Enio Alves Borges  
**Instituição:** Banco do Brasil

## Licença

GPL v2 ou posterior – veja o arquivo [LICENSE](LICENSE).

## Changelog

### 1.1 (2026-04-09)
- Correção na busca avançada (suporte a múltiplos termos)
- Melhorias na interface administrativa
