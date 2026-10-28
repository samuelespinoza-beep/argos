SAIL := ./vendor/bin/sail
APP_PORT ?= 8070

# Chrome de depuracion propio de Argos (puerto y perfil distintos a los de
# cronos-social-scraper, para que abrir uno no cierre la ventana del otro).
CHROME_PORT ?= 9226
CHROME_PROFILE ?= $(HOME)/.chrome-debug-argos

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-14s\033[0m %s\n", $$1, $$2}'

dev: ## Levanta Argos con Sail en segundo plano (app, Postgres, Redis y el scheduler del scraping diario)
	@$(SAIL) up -d
	@echo "OK: Argos en http://localhost:$(APP_PORT) · scraping diario a las 06:00 (Lima) mientras esto siga arriba"

down: ## Apaga los contenedores (los datos de la base se conservan). Ojo: sin contenedores no corre el scraping de las 06:00
	@$(SAIL) down

restart: ## Reinicia los contenedores
	@$(SAIL) restart

ps: ## Estado de los contenedores
	@$(SAIL) ps

logs: ## Logs en vivo de los contenedores (Ctrl+C para salir). Uno solo: make logs S=scheduler
	@$(SAIL) logs -f $(S)

clear: ## exec: artisan [config:clear + route:clear + view:clear + cache:clear]
	@$(SAIL) artisan config:clear
	@$(SAIL) artisan route:clear
	@$(SAIL) artisan view:clear
	@$(SAIL) artisan cache:clear

install: ## Instala las dependencias de composer
	@$(SAIL) composer install

migrate: ## Corre las migraciones pendientes
	@$(SAIL) artisan migrate

test: ## exec: artisan test
	@$(SAIL) artisan test

shell: ## Abre una terminal dentro del contenedor de la app
	@$(SAIL) shell

scrape: ## Scrapea todos los sitios ahora (foto del dia). Uno solo: make scrape SITIO=cuponatic
	@$(SAIL) artisan argos:scrape $(SITIO)

vista: ## HTML de ofertas con los cambios desde la foto anterior. Uso: make vista SITIO=cuponatic
	@$(SAIL) artisan argos:vista $(or $(SITIO),cuponatic)

vistas: ## Genera la vista de Cuponidad y la de Cuponatic
	@$(SAIL) artisan argos:vista cuponidad
	@$(SAIL) artisan argos:vista cuponatic

front: ## Vite en modo desarrollo: la app Vue se recarga al guardar (Ctrl+C para salir)
	@$(SAIL) npm run dev

build: ## Compila la app Vue para usarla sin "make front"
	@$(SAIL) npm run build

exportar: ## Excel + CSV Cuponidad vs competidor. Uso: make exportar SITIO=cuponatic
	@$(SAIL) artisan argos:exportar $(or $(SITIO),cuponatic)

chrome: ## Abre el Chrome de depuracion (puerto 9226) que Claude puede ver, para scraping
	@echo "Cerrando cualquier ventana de depuracion anterior de Argos..."
	@pkill -f "[r]emote-debugging-port=$(CHROME_PORT)" 2>/dev/null; sleep 2; true
	@echo "Abriendo Chrome de depuracion en el puerto $(CHROME_PORT)..."
	@setsid google-chrome --remote-debugging-port=$(CHROME_PORT) --user-data-dir="$(CHROME_PROFILE)" --no-first-run --no-default-browser-check </dev/null >/dev/null 2>&1 & disown; \
	sleep 4; \
	curl -s http://localhost:$(CHROME_PORT)/json/version >/dev/null 2>&1 && echo "OK: Chrome activo. Navega en esa ventana y pidele a Claude que la lea." || echo "ERROR: no arranco"

chrome-stop: ## Cierra la ventana de Chrome de depuracion de Argos (no toca tu Chrome normal)
	@pkill -f "[r]emote-debugging-port=$(CHROME_PORT)" 2>/dev/null && echo "Ventana de depuracion cerrada" || echo "No habia ninguna abierta"

.DEFAULT_GOAL := help
.PHONY: help dev down restart ps logs clear install migrate test shell scrape vista vistas front build exportar chrome chrome-stop
