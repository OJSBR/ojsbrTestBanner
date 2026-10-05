# Test Environment Banner — OJS plugin

[![OJS](https://img.shields.io/badge/OJS-3.5-brightgreen)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-1.1.0.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OJS 3.5](https://github.com/OJSBR/ojsbrTestBanner/releases/download/1.1.0.0/ojsbrTestBanner-1.1.0.0.tar.gz) — or browse all [Releases](../../releases).

A site-wide plugin for **Open Journal Systems (OJS)** that marks a test, staging or training
installation with a **fixed red band at the bottom of every page** — the site, every journal and the
editorial back end — so nobody mistakes it for the live portal. The message is yours, in every
language of the site.

> **Developed and maintained by [OJSBR](https://ojsbr.com).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OJS version | Branch | Plugin release |
|-------------|--------|----------------|
| OJS 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.1.0.0 |

38 languages.

## The problem

A copy of a live portal — for testing an upgrade, training editors or trying a plugin — looks
exactly like the real one: same journals, same articles, often the same users. Editors log in to
the wrong one and do real work there, authors submit to it, readers land on it from a link shared
by mistake. A theme change per copy is easy to forget and is lost on the next upgrade.

## What it does

- A **red band fixed to the bottom of the window** on every page of the installation: the site
  index, every journal, and the editorial back end (submissions, workflow, settings).
- **Your message, per language.** Written by the site administrator in the plugin settings, one per
  language of the site — e.g. *"Staging — use journals.example.org"*. A reader whose language has
  no message sees the one of the site's primary language; with no message at all, the translated
  default: *"Test environment — data on this installation is not official"*.
- **One switch for the whole installation.** The plugin is site-wide: enabled once, it covers every
  journal, and only the site administrator can switch it off or change the message — a journal
  manager cannot hide it.
- **Readers are left alone.** The full-page galley viewers (PDF.js, the EPUB reader, HTML galleys,
  Lens) get no band, so it never covers the document. The band is not printed.
- Nothing is loaded from outside, no script is added: a few lines of CSS in the page.

## Installation

1. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the folder into
   `plugins/generic/` so that you get `plugins/generic/ojsbrTestBanner/`. Do not rename the folder:
   OJS derives the plugin's class namespace from the directory name.
2. Log in as **site administrator** and enable **Test environment banner**:
   - on a site with **several journals**: **Administration → Site Settings → Plugins**;
   - on a site with **a single journal**, where OJS 3.5 hides the plugins tab of the site settings:
     **Settings → Website → Plugins** of the journal. Site-wide plugins are listed there, and only
     the site administrator can enable them or open their settings.

## Configuration

In the plugin **Settings**:

| Setting | Default | |
|---|---|---|
| Message | *Test environment — data on this installation is not official* (translated) | Plain text, up to 200 characters, one per language of the site. Markup and line breaks are removed. |

## How it works (technical)

- **Site-wide.** `isSitePlugin()` returns `true` and `version.xml` declares `<sitewide>1</sitewide>`:
  OJS keeps the plugin's state and settings at the site level (`context_id` NULL), loads it in every
  journal, and lets only the site administrator manage it (the core `PluginAccessPolicy`).
- **The band** is an inline stylesheet added through `TemplateManager::addStyleSheet()` in the
  `TemplateManager::display` hook, for the `frontend` and `backend` contexts, last in the sequence:
  a `body::after` pseudo-element fixed to the bottom of the window, with `pointer-events: none`, and
  a bottom padding on `body` so the end of the page is not covered. No template of the core or of the
  theme is replaced.
- **The message in the CSS.** Every character that is not a letter, a digit or a space is written as
  a CSS escape (`\22 ` for `"`), so no message can close the CSS string or the `<style>` element.
- **Galley viewers** are recognised by the template OJS is about to display, which arrives as a
  Smarty resource id with hyphens for slashes
  (`plugins-23-plugins-generic-pdfJsViewer-generic-pdfJsViewer:display.tpl`); the band is not added
  for `pdfJsViewer`, `epubJsViewer`, `htmlArticleGalley` and `lensGalley`.
- **Settings** use the classic plugin settings form (`AjaxModal`, `FormValidatorPost`,
  `FormValidatorCSRF`), so it works on every OJS 3.5.0.x. The messages are stored as one JSON setting.
- **Upgrading from 1.0.0.0** (distributed privately before the first public release, with a fixed
  message): the band keeps working, and is now switched on and off for the whole site only.

## Tests

- **PHPUnit** (`tests/*Test.php`, on `PKP\tests\PKPTestCase`): the message saved by the settings form
  is **read back from the database** and is exactly what the page gets, with the hook run the way the
  core runs it and the stylesheet printed as the core prints it — on a journal page, on the site
  index and in the back end, in the language of the reader with the primary language and the default
  as fallbacks; no band on the PDF, EPUB and HTML galley viewers; a message over 200 characters
  refused; and ten hostile messages (quotes, backslash, `</style><script>`, a comment, braces, line
  breaks, other scripts, emoji) that must come out as one closed CSS string saying exactly the
  message. Also the classes against the installed PKP, the templates and the 38 translations.
  Counterproof: with the fixed message and the escaping of 1.0.0.0, 13 tests fail. From the OJS root:

  ```bash
  lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage "$PWD/plugins/generic/ojsbrTestBanner/tests"
  ```

- **Cypress** (`cypress/tests/functional/OjsbrTestBanner.cy.js`, run by
  [pkp-github-actions](https://github.com/pkp/pkp-github-actions) on every push): the site
  administrator enables the plugin, writes the message in the settings and **reads it back from a
  form the server sends again** (a message of 201 characters is refused and the saved one stays);
  the band, as the browser renders it, says that message at the bottom of a journal page and in the
  editorial back end; a PDF galley viewer has no band; a journal manager who tries to switch it off is
  refused and the band stays; with the plugin off no page has it. The plugin and its message are put
  back as they were. Counterproof: with the message ignored or the viewers
  not recognised, the matching tests fail.
- Verified on OJS 3.5.0.3 (one journal, three languages) and, in 1.0.0.0, on a portal of 37 journals.

Tests are kept in the repository and are not part of the release package.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com) — original plugin.
- Distributed under the **GNU GPL v3**.

## AI use

Generative AI (Claude, by Anthropic) was used to write and run tests, improve the code and bring
it in line with PKP standards. Every change is reviewed and tested by OJSBR, which is responsible
for the published releases.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OJS version you are
working against. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

Plugin de portal para o **Open Journal Systems (OJS)** que marca uma instalação de testes,
homologação ou treinamento com uma **tarja vermelha fixa no rodapé de todas as páginas** — o
portal, todas as revistas e a área administrativa —, para ninguém confundir com o portal de
produção. A mensagem é sua, em todos os idiomas do site.

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com).** Veja a seção
> [Créditos e autoria](#créditos-e-autoria) abaixo.

### Compatibilidade e branches

| Versão do OJS | Branch | Release do plugin |
|---------------|--------|-------------------|
| OJS 3.5.x     | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.1.0.0 |

38 idiomas.

### O problema

Uma cópia do portal de produção — para testar uma atualização, treinar editores ou experimentar um
plugin — é idêntica à verdadeira: as mesmas revistas, os mesmos artigos, muitas vezes os mesmos
usuários. Editores entram na errada e fazem trabalho de verdade ali, autores submetem nela, leitores
chegam por um link compartilhado por engano. Mudar o tema em cada cópia é fácil de esquecer e se
perde na próxima atualização.

### O que faz

- **Tarja vermelha fixa no rodapé da janela** em todas as páginas da instalação: a página inicial do
  portal, todas as revistas e a área administrativa (submissões, fluxo editorial, configurações).
- **A mensagem é sua, por idioma.** Escrita pelo administrador do site nas configurações do plugin,
  uma por idioma do site — por exemplo, *"Homologação — use revistas.exemplo.br"*. Quem lê num
  idioma sem mensagem vê a do idioma principal do site; sem mensagem nenhuma, vale o texto padrão
  traduzido: *"Ambiente de testes — os dados desta instalação não são oficiais"*.
- **Um interruptor para a instalação inteira.** O plugin é de portal: ligado uma vez, cobre todas as
  revistas, e só o administrador do site pode desligá-lo ou mudar a mensagem — o gestor de uma
  revista não consegue esconder a tarja.
- **O leitor não é atrapalhado.** Os leitores de composição em página inteira (PDF.js, o leitor de
  EPUB, composições HTML, Lens) ficam sem a tarja, para ela nunca cobrir o documento. A tarja não sai
  na impressão.
- Nada é carregado de fora e nenhum script é acrescentado: só algumas linhas de CSS na página.

### Instalação

1. Instale por **Configurações → Website → Plugins → Enviar novo plugin**, ou extraia a pasta em
   `plugins/generic/`, ficando `plugins/generic/ojsbrTestBanner/`. Não renomeie a pasta: o OJS deriva
   o namespace da classe do nome do diretório.
2. Entre como **administrador do site** e ative **Tarja de ambiente de testes**:
   - em instalação com **várias revistas**: **Administração → Configurações do Portal → Plugins**;
   - em instalação com **uma revista só**, em que o OJS 3.5 esconde a aba de plugins do portal:
     **Configurações → Website → Plugins** da revista. Os plugins de portal aparecem ali, e só o
     administrador do site consegue ativá-los ou abrir as configurações deles.

### Configuração

Nas **Configurações** do plugin:

| Opção | Padrão | |
|---|---|---|
| Mensagem | *Ambiente de testes — os dados desta instalação não são oficiais* (traduzida) | Texto simples, até 200 caracteres, uma por idioma do site. Marcação e quebras de linha são removidas. |

### Como funciona (técnico)

- **De portal.** `isSitePlugin()` devolve `true` e o `version.xml` declara `<sitewide>1</sitewide>`:
  o OJS guarda o estado e as configurações do plugin no nível do site (`context_id` NULL), carrega o
  plugin em todas as revistas e só deixa o administrador do site gerenciá-lo (a `PluginAccessPolicy`
  do núcleo).
- **A tarja** é uma folha de estilo embutida, acrescentada por `TemplateManager::addStyleSheet()` no
  gancho `TemplateManager::display`, nos contextos `frontend` e `backend`, por último na sequência:
  um pseudoelemento `body::after` fixo no rodapé da janela, com `pointer-events: none`, e um espaço
  no fim do `body` para o final da página não ficar coberto. Nenhum template do núcleo ou do tema é
  substituído.
- **A mensagem dentro do CSS.** Todo caractere que não é letra, dígito ou espaço é escrito como
  escape de CSS (`\22 ` para `"`), de modo que mensagem nenhuma consegue fechar a string do CSS nem o
  elemento `<style>`.
- **Os leitores de composição** são reconhecidos pelo template que o OJS vai exibir, que chega como
  identificador de recurso do Smarty, com hífens no lugar das barras
  (`plugins-23-plugins-generic-pdfJsViewer-generic-pdfJsViewer:display.tpl`); a tarja não entra em
  `pdfJsViewer`, `epubJsViewer`, `htmlArticleGalley` e `lensGalley`.
- **As configurações** usam o formulário clássico de plugin (`AjaxModal`, `FormValidatorPost`,
  `FormValidatorCSRF`), que funciona em todo OJS 3.5.0.x. As mensagens ficam numa configuração JSON.
- **Quem atualiza da 1.0.0.0** (distribuída de forma privada antes da primeira versão pública, com
  mensagem fixa): a tarja continua funcionando e passa a ser ligada e desligada só para o site inteiro.

### Testes

- **PHPUnit** (`tests/*Test.php`, sobre `PKP\tests\PKPTestCase`): a mensagem salva pelo formulário de
  configurações é **lida de volta do banco** e é exatamente o que a página recebe, com o gancho
  disparado como o núcleo dispara e a folha de estilo impressa como o núcleo imprime — numa página de
  revista, na página inicial do portal e na área administrativa, no idioma do leitor, com o idioma
  principal e o texto padrão como reserva; nenhuma tarja nos leitores de PDF, EPUB e HTML; mensagem
  com mais de 200 caracteres recusada; e dez mensagens hostis (aspas, barra invertida,
  `</style><script>`, comentário, chaves, quebra de linha, outras escritas, emoji) que têm de sair como
  uma string de CSS fechada dizendo exatamente a mensagem. Também as classes contra o PKP instalado,
  os templates e os 38 idiomas. Contraprova: com a mensagem fixa e o escape da 1.0.0.0, 13 testes
  reprovam.
- **Cypress** (`cypress/tests/functional/OjsbrTestBanner.cy.js`): o administrador do site liga o
  plugin, escreve a mensagem nas configurações e **a lê de volta num formulário que o servidor manda de
  novo** (uma mensagem de 201 caracteres é recusada e a salva continua); a tarja, como o navegador a
  desenha, diz essa mensagem no rodapé de uma página da revista e na área administrativa; o leitor de
  PDF fica sem tarja; o gestor de revista que tenta desligá-la é recusado e a tarja continua; com o
  plugin desligado, nenhuma página a tem. O plugin e a mensagem voltam ao
  que eram. Contraprova: com a mensagem ignorada ou sem reconhecer os leitores, os testes
  correspondentes reprovam.
- Conferido no OJS 3.5.0.3 (uma revista, três idiomas) e, na 1.0.0.0, num portal de 37 revistas.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com) — plugin original.
- Distribuído sob a **GNU GPL v3**.

### Uso de IA

Foi usada IA generativa (Claude, da Anthropic) para escrever e rodar testes, melhorar o código e
alinhá-lo aos padrões da PKP. Toda mudança é revisada e testada pela OJSBR, que responde pelas
releases publicadas.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
