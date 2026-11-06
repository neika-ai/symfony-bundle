# Changelog

## v0.2.0

**Breaking: the env vars now start with `NEIKA_`.** Rename them in `.env.local`:

```diff
-CHATBOT_BACKEND_URL=https://neika.ai
-CHATBOT_API_SECRET=…
-CHATBOT_INGEST_SECRET=…
-CHATBOT_SITE_KEY=…
+NEIKA_CHATBOT_BACKEND_URL=https://neika.ai
+NEIKA_CHATBOT_API_SECRET=…
+NEIKA_CHATBOT_INGEST_SECRET=…
+NEIKA_CHATBOT_SITE_KEY=…
```

Also rename `CHATBOT_BACKEND_URL` in `config/packages/neika.yaml`. An old secret name left in place → empty
secret, so every `/chatbot/v1` call gets a 401 and nothing is sent to Neika.

- `GET /chatbot/v1/tools` lists each tool's arguments marked `#[Sensitive]`.

## v0.1.0

First release under [neika-ai](https://github.com/neika-ai).
