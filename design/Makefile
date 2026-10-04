# html-dev-server: Caddy (HTTPS, gzip/zstd, HTTP/2, cache) serving the prototype (clone)
# and the source copy side by side. Source: html-dev-server skill.
NAME       := axell-atelier-caddy
CLONE      := $(CURDIR)/bootstrap
SOURCE     := $(CURDIR)/source
SERVE      := $(CURDIR)/.serve
CERTS      := $(SERVE)/certs
IMAGE      := caddy:2
CLONE_URL  := https://localhost:8543
SOURCE_URL := https://localhost:8544

.DEFAULT_GOAL := help
.PHONY: help cert sync up down restart logs status open

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

cert: ## Gera certificado local (mkcert) se ainda não existir
	@command -v mkcert >/dev/null || { echo "brew install mkcert && mkcert -install"; exit 1; }
	@mkdir -p $(CERTS)
	@test -f $(CERTS)/localhost.pem || mkcert -cert-file $(CERTS)/localhost.pem -key-file $(CERTS)/localhost-key.pem localhost 127.0.0.1 ::1

sync: ## Copia atelier-axell-club.html para source/index.html e assets/ para clone e source
	@mkdir -p $(SOURCE)
	@cp $(CURDIR)/atelier-axell-club.html $(SOURCE)/index.html
	@rm -rf $(SOURCE)/assets $(CLONE)/assets && cp -R $(CURDIR)/assets $(SOURCE)/assets && cp -R $(CURDIR)/assets $(CLONE)/assets

up: cert sync ## Sobe clone (:8543, bootstrap/) e source (:8544, source/)
	@docker rm -f $(NAME) >/dev/null 2>&1 || true
	@docker run -d --name $(NAME) \
		-p 8543:8543 -p 8544:8544 \
		-v "$(CLONE)":/clone:ro \
		-v "$(SOURCE)":/source:ro \
		-v "$(SERVE)/Caddyfile":/etc/caddy/Caddyfile:ro \
		-v "$(CERTS)":/certs:ro \
		$(IMAGE) >/dev/null
	@echo "▲ clone:  $(CLONE_URL)"
	@echo "▲ source: $(SOURCE_URL)"

down: ## Derruba o servidor
	@docker rm -f $(NAME) >/dev/null 2>&1 && echo "▼ parado" || echo "já estava parado"

restart: ## Reinicia (down + up)
	@$(MAKE) --no-print-directory down && $(MAKE) --no-print-directory up

logs: ## Segue os logs do container
	@docker logs -f $(NAME)

status: ## Mostra status do container e URLs
	@docker ps --filter name=$(NAME) --format 'status: {{.Status}}\nports:  {{.Ports}}'
	@echo "clone:  $(CLONE_URL)\nsource: $(SOURCE_URL)"

open: ## Abre clone e source no navegador
	@open $(CLONE_URL) && open $(SOURCE_URL)
