# Bitrix Dev

---

- Просмотр и поиск статей
- Категории и теги
- REST API

---

### Backend
- Laravel
- MariaDB
- Eloquent ORM
- Laravel Auth
- Storage

### Frontend
- Vite
- Vue
- Tailwind CSS

### Infrastructure
- Nginx
- PHP-FPM
- Ubuntu

## Ollama test consultant

The test chat is available at `/test-chat`. It currently uses the short rules in `resources/ai/system_prompt_simple.txt` and sends plain text to and from Ollama. The detailed prompt and product catalog are retained in `resources/ai/` but are not loaded by this simple mode.

Set `OLLAMA_URL` and the exact installed `OLLAMA_MODEL` in `.env` (for example, `http://127.0.0.1:11434` and `qwen2.5:0.5b-instruct`), then run `php artisan config:clear` after deployment.

The simple mode has no product lookup, live price/stock source, listing-ID mapping, verified technical documents, or manager-ticket integration. Do not use it to confirm a product's fitment or current availability. Those integrations need to be implemented and connected separately.

## OpenAI prompt endpoint

`POST /api/ai` accepts a prompt, optional recent conversation, and required normalized `event` metadata. It requires `Authorization: Bearer <AI_API_TOKEN>` and is limited to 10 requests per minute per client IP. `event.attachment_state` must be `none` or `complete` before a text request reaches policy, catalog, or OpenAI; unknown attachment metadata is held for the connector to resolve.

The Bitrix adapter must set `is_customer_message`, `message_id`, `attachment_state`, attachment MIME/kind/filename metadata, `human_takeover`, `already_processed`, and handoff status from trusted connector state. It may set `current_offer_id` only after a verified platform-listing-to-internal-offer mapping; Avito listing IDs are not automatically product IDs. For order extraction, include real customer `message_id` values in `conversation`; the app rejects extracted data with unknown source message IDs.

The endpoint loads `resources/ai/system_prompt_openai_v2.txt`, `store_policy.json`, `response_schema.json`, and the verified snapshot under `resources/ai/snapshot/`. It searches the 1,780-record snapshot only for product-related prompts or a confirmed internal `current_offer_id`, then sends at most three compact matching cards with separate price and inventory snapshot blocks. The old `resources/ai/catalog.jsonl` remains for reference and is not used by this OpenAI path. `order_flow.txt` is added only for order, payment, or shipping questions. Technical articles have not been ingested.

Simple greetings and single-intent policy FAQs use local templates without calling OpenAI. The snapshot is not live stock: direct current-availability claims and same-day pickup guarantees are disabled until a live inventory/manager check exists.

Configure these values in the server `.env`; never put either secret in frontend code:

```dotenv
OPENAI_API_KEY=your-new-openai-api-key
OPENAI_MODEL=gpt-4o-mini
AI_API_TOKEN=your-long-random-client-token
```

The ChatGPT Pro subscription does not include API usage; the OpenAI API account must have API billing enabled. Example request:

```bash
curl -X POST https://trickster.zammensiny.ru/api/ai \
	-H 'Authorization: Bearer YOUR_AI_API_TOKEN' \
	-H 'Content-Type: application/json' \
	-d '{"prompt":"Здравствуйте! Подойдет ли деталь для моей машины?","event":{"is_customer_message":true,"message_id":"test-message-1","attachment_state":"none","attachments":[],"human_takeover":false,"already_processed":false,"handoff_status":"not_requested"}}'
```

After changing `.env`, run `php artisan config:clear` and `php artisan config:cache` on the server.

The endpoint can route photo/document events and return `request_handoff`, but it cannot transfer a Bitrix session, persist takeover, deduplicate messages, or save order state by itself. The Bitrix adapter must send trustworthy message IDs, attachment metadata and manager state, then execute and confirm handoff before claiming success. Do not connect automatic buyer replies until that adapter supplies these facts.

---

