=== WP AI Publisher ===
Contributors: garrequintanilha
Tags: ai, artificial intelligence, openai, gemini, anthropic, content, posts, automation
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automação inteligente de criação e agendamento de posts com texto e imagens gerados por IA.

== Description ==

O WP AI Publisher integra os principais modelos de linguagem (LLMs) e geradores de imagem diretamente no editor do WordPress — Clássico e Gutenberg.

**Funcionalidades:**

* Painel de configurações com suporte a OpenAI, Google Gemini, Anthropic Claude e DeepSeek
* Criptografia AES-256 de todas as chaves de API
* Metabox lateral no editor para geração de rascunhos, expansão e resumo de texto
* Geração de imagem de destaque com DALL-E 3 ou Gemini Imagen
* Inserção de imagens ilustrativas direto no corpo do post
* Motor de agendamento via WP-Cron para publicação autônoma de posts
* Log de execuções dos agendamentos

== Installation ==

1. Envie a pasta `wp-ai-publisher` para `/wp-content/plugins/`
2. Ative o plugin em **Plugins > Plugins instalados**
3. Acesse **AI Publisher > Configurações** e insira suas chaves de API
4. Edite um post e use o painel lateral **🤖 AI Publisher**

== Frequently Asked Questions ==

= As chaves de API ficam seguras? =
Sim. Todas as chaves são criptografadas com AES-256-CBC antes de serem salvas no banco.

= Funciona com o editor clássico? =
Sim. O plugin é compatível com Editor Clássico (TinyMCE) e Gutenberg.

= O custo das APIs é incluso? =
Não. Os custos de uso das APIs (OpenAI, Google, Anthropic, DeepSeek) são de responsabilidade direta do cliente.

== Changelog ==

= 1.0.0 =
* Lançamento inicial.
