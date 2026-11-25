# SisREAd com explicabilidade

Esta é a versão do SisREAd, um sistema de recomendação de Recursos Educacionais Abertos (REAs), usada no Trabalho de Conclusão de Curso de Rafael Zimbrão, do Bacharelado em Ciência da Computação da Universidade Federal de Juiz de Fora (UFJF). O repositório guarda o sistema no estado descrito na monografia e não recebe mais alterações.

A versão anterior do SisREAd, desenvolvida por Gabriel do Carmo Silva, está em [GabrielCarmoSilva/sistema-de-recomendacao-reas](https://github.com/GabrielCarmoSilva/sistema-de-recomendacao-reas). Este trabalho partiu dela e acrescentou funcionalidades de explicabilidade.

## O que o sistema faz

O usuário informa a etapa de ensino, o tema e a meta de realização. O sistema então busca REAs em três fontes:

- **Aquarela**, um repositório de REAs;
- **MEC RED**, a Plataforma MEC de Recursos Educacionais Digitais;
- **Eduplay**, uma plataforma de vídeos educacionais.

Os resultados são ordenados por um grau de recomendação, calculado a partir dos critérios que cada recurso atende: tema, nível, tipo preferido e meta de realização. Um modelo de linguagem classifica a meta de realização de cada recurso.

As funcionalidades de explicabilidade ficam atrás de flags e se agrupam em três versões, usadas no estudo com usuários:

| Versão | O que o usuário vê |
|---|---|
| `controle` | A lista de recomendações, sem explicações. |
| `transparencia` | Por que cada REA aparece, os painéis de ordenação e de contexto, os REAs excluídos e o progresso da busca. |
| `escrutabilidade` | Tudo o que a transparência mostra e, além disso, a correção das estimativas do sistema (nível, meta e tipos preferidos), que reordena a lista. |

A versão é escolhida pelo parâmetro `?grupo=<versão>` na URL. Sem o parâmetro, o sistema usa a versão `escrutabilidade`.

## Tecnologias

- PHP 8.3, Laravel 11 e Livewire 3
- SQLite
- Tailwind CSS (por CDN) e Alpine.js
- Ollama ou Groq para a classificação de meta por modelo de linguagem

## Como rodar

Requisitos: PHP 8.3 com as extensões `pdo_sqlite`, `mbstring`, `intl` e `zip`, e o Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

O repositório já traz um banco vazio em `database/database.sqlite`, com as tabelas criadas e só os motivos fixos da avaliação. Para recriá-lo do zero:

```bash
rm database/database.sqlite && touch database/database.sqlite
php artisan migrate --seed
```

A busca roda em jobs de fila. Por isso, é preciso subir o servidor e o worker, cada um em um terminal:

```bash
php artisan serve
php artisan queue:work --timeout=600 --tries=3
```

A aplicação fica em http://127.0.0.1:8000.

### Modelo de linguagem

A variável `LLM_PROVEDOR` define quem classifica a meta dos REAs:

- `ollama` (padrão): usa um modelo local. Instale o [Ollama](https://ollama.com) e baixe o modelo com `ollama pull gemma3:4b`.
- `groq`: usa a API do Groq. Defina `GROQ_API_KEY` com uma chave criada em [console.groq.com](https://console.groq.com/keys).
- `nenhum`: desliga a classificação. A meta dos REAs não é avaliada.

### Comandos do experimento

```bash
php artisan experimento:grupo <usuario> [grupo]   # consulta ou fixa a versão de um usuário
php artisan experimento:exportar --por-grupo      # exporta as métricas das buscas em CSV
php artisan experimento:aquecer                   # pré-carrega o cache da classificação de meta
```

## Testes

```bash
php artisan test
```

## Docker

O `Dockerfile` gera uma imagem com servidor web, worker e agendador. O `docker-compose.prod.yml` sobe essa imagem com HTTPS automático. Para usá-lo, copie `.env.production.example` para `.env.production`, preencha `APP_KEY` e `SERVER_NAME` e rode:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

O banco fica em `./data`, fora da imagem.
