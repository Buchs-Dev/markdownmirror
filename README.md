# Markdown Mirror — Joomla System Plugin

A Joomla 5/6 system plugin that automatically serves pages as Markdown to AI agents, without any changes to your content or templates.

## How it works

On every front-end page request the plugin checks whether the client wants Markdown. If so, it converts the rendered HTML to Markdown and returns it instead of the normal HTML response, along with a YAML frontmatter block containing page metadata.

Markdown is served automatically when the `User-Agent` matches a known AI crawler (GPTBot, ClaudeBot, PerplexityBot, etc.), or when the client sends `Accept: text/markdown`. The query parameter `?md=1` can also be used as a manual trigger for testing.

The response sets `Content-Type: text/markdown`, `X-Robots-Tag: noindex, follow`, `Vary: Accept`, and `X-Markdown-Tokens` (an estimated token count).

## Output format

```
---
title: Page title
url: https://example.com/page
description: Meta description
date: 2024-01-15T12:00:00+00:00
---

# Page title

Article body converted to Markdown…
```

Only the `<article>` element is extracted and converted. Navigation, headers, footers, sidebars, scripts, and styles are stripped.

## Requirements

- Joomla 5.x or 6.x
- PHP 8.1+

## Installation

1. Download or clone this repository.
2. Run `composer install --no-dev` to install the vendor dependency.
3. Zip the directory and install it via **System → Extensions → Install** in the Joomla back end.
4. Enable the plugin under **System → Plugins → System - Markdown Mirror**.

## Development

```bash
composer install
```

The only runtime dependency is [`league/html-to-markdown`](https://github.com/thephpleague/html-to-markdown).

## Version

Current version: **0.2.0** — see `markdownmirror.xml` for the changelog and update server URL.