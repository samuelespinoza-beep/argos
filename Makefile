# Chrome de depuracion propio de Argos (puerto y perfil distintos a los de
# cronos-social-scraper, para que abrir uno no cierre la ventana del otro).
CHROME_PORT ?= 9226
CHROME_PROFILE ?= $(HOME)/.chrome-debug-argos

chrome: ## Abre el Chrome de depuracion (puerto 9226) que Claude puede ver, para scraping
	@echo "Cerrando cualquier ventana de depuracion anterior de Argos..."
	@pkill -f "[r]emote-debugging-port=$(CHROME_PORT)" 2>/dev/null; sleep 2; true
	@echo "Abriendo Chrome de depuracion en el puerto $(CHROME_PORT)..."
	@setsid google-chrome --remote-debugging-port=$(CHROME_PORT) --user-data-dir="$(CHROME_PROFILE)" --no-first-run --no-default-browser-check </dev/null >/dev/null 2>&1 & disown; \
	sleep 4; \
	curl -s http://localhost:$(CHROME_PORT)/json/version >/dev/null 2>&1 && echo "OK: Chrome activo. Navega en esa ventana y pidele a Claude que la lea." || echo "ERROR: no arranco"

.PHONY: chrome
