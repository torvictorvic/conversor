# Conversor CLI PHP 7.4

Conversor de montos **ARS/COP hacia USD** por línea de comandos. Consulta **dos proveedores de APIs** en paralelo:
- **Open.er**: https://open.er-api.com/v6/latest/USD (base USD)
- **Fixer**: https://data.fixer.io/api/latest (base EUR, requiere API key)

Funciones incorporadas:
- Manejo de errores por proveedor.
- **Cache local** con TTL por proveedor.
- **Logging** (access y error) en JSON por línea.
- Banderas de CLI (`--format`, `--providers`, `--ttl`).
- Empaquetado **PHAR** para distribuir como binario único.

> Probado en **PHP 7.4 CLI**, sin frameworks, sin Composer.

---

## Requisitos

- PHP **7.4** (CLI) con extensiones:
  - `curl`
  - `openssl`
- Acceso a red para las APIs.

> Verifica: `php -v` y `php -m | grep -E 'curl|openssl'`

---

## Instalación

1. Clona o copia el proyecto: https://github.com/torvictorvic/conversor
2. Crea tu archivo de entorno:
   ```bash
   cp .env.example .env
   # edita tu FIXER_API_KEY en .env
   ```
3. (Opcional) prepara la carpeta de logs:
   ```bash
   mkdir -p logs
   ```

---

## Ejecución rápida

### Usando el script PHP
```bash
php conversor.php 100 ARS
php conversor.php 250000 COP
```

### Usando el binario PHAR
```bash
./conversor.phar 100 ARS
./conversor.phar 250000 COP
```

> La zona horaria se fija a `America/Argentina/Buenos_Aires` en `conversor.php` para el sello de fecha/hora.

---

## Formatos de salida

### Tabla (por defecto)
```
Open.er (open.er-api.com): 1.02 USD
Fixer (data.fixer.io): 1.03 USD

Nota:
Valor por cambio por 1 USD en Open.er es = 3 912.45 COP
Valor por cambio por 1 USD en Fixer es  = 3 885.10 COP

Fecha Actualizacion: 2025-09-17 09:17
```

### JSON
```bash
php conversor.php --format=json 100 ARS
```
```json
{
  "amount": 100.0,
  "currency": "ARS",
  "results": [
    {
      "provider": "OpenErApi",
      "source": "Open.er (open.er-api.com)",
      "usd": 0.08,
      "rate": 1234.56,
      "date": "Wed, 17 Sep 2025 09:15:01 +0000"
    },
    {
      "provider": "FixerApi",
      "source": "Fixer (data.fixer.io)",
      "usd": 0.07,
      "rate": 1160.00,
      "date": "2025-09-17"
    }
  ],
  "updated_at": "2025-09-17 09:17"
}
```

> `rate` es **MONEDA por 1 USD** (ej.: COP por USD). `usd` es el monto convertido solicitado.

---

## Banderas de CLI

```
php conversor.php [--format=table|json] [--providers=open,fixer] [--ttl=60] <monto> <moneda>
```

- `--format`  : `table` (default) o `json`  
- `--providers`: lista separada por comas. `open`, `fixer`.  
  Ej.: `--providers=open` o `--providers=fixer` o ambos `--providers=open,fixer`
- `--ttl`     : segundos de cache por proveedor (default 60).

Monedas soportadas por defecto: `ARS`, `COP`.

Códigos de salida:
- `0` OK
- `2` error de uso/validación (args/moneda)
- Otros: errores internos no capturados

---

## Variables de entorno (`.env`)

```
FIXER_API_KEY=TU_API_KEY_DE_FIXER
LOG_DIR=logs
# RATE_TTL=60     # TTL por defecto si no se pasa --ttl en los paramss
```

- `FIXER_API_KEY` **(obligatoria para Fixer)**.
- `LOG_DIR`: carpeta base para logs (por defecto `./logs`). Si no existe o no es escribible, se usa `sys_get_temp_dir()`.
- `RATE_TTL`: TTL por defecto para el cache si no especificas `--ttl`.

---

## Logs

Se generan archivos diarios en `LOG_DIR` con formato **JSON por línea**.

- **Access**: `access-YYYY-MM-DD.log`  
  Ejemplo:
  ```json
  {"ts":"2025-09-17T09:22:41+00:00","exec_id":"a1b2c3d4","url":"https://open.er-api.com/v6/latest/USD","status":200,"ms":112,"bytes":12345,"error":null}
  ```

- **Error**: `error-YYYY-MM-DD.log`  
  Ejemplos:
  ```json
  {"ts":"2025-09-17T09:22:41+00:00","exec_id":"a1b2c3d4","message":"HTTP non-2xx","url":"https://data.fixer.io/...","status":401,"ms":98}
  {"ts":"2025-09-17T09:22:41+00:00","exec_id":"a1b2c3d4","message":"Provider error","provider":"App\Providers\FixerApi","currency":"COP","amount":4000,"error":"Fixer: respuesta inválida"}
  ```

> `exec_id` permite correlacionar todas las trazas de una ejecución.

---

## Cache

El cache es por **proveedor + moneda** y maneja en `sys_get_temp_dir()/currency-cache`.
Se controla por `--ttl` (segundos) o `RATE_TTL` en `.env`.
Cada entrada guarda la tasa completa del proveedor y se marca como “(cache)” en la salida cuando aplica.

---

## Empaquetado PHAR

Construcción (requiere habilitar creación de phars en tiempo de ejecución):
```bash
php -d phar.readonly=0 build-phar.php
./conversor.phar 100 ARS
```

El `build-phar.php` incluye `conversor.php` y todo `src/` (excluye `logs/`, el propio phar y el builder).

---

## Estructura del proyecto (resumen)

```
___ conversor.php
___ conversor.phar               
___ build-phar.php
___ .env.example
___ src/
___ /___ Env.php
___ /___ HttpClient.php
___ /___ Log.php
___ /___ Cache.php
___ /___ Converter.php
___ /___ Providers/
___ /___/___ RateProvider.php
___ /___/___ OpenErApi.php
___ /___/___ FixerApi.php
___ /___/___ CachedProvider.php
___ logs/
```

---


## Ejemplos de usos

```bash
# Modo tabla (default)
php conversor.php 100 ARS

# Solo Open.er y TTL de 5 minutos
php conversor.php --providers=open --ttl=300 250000 COP

# JSON (ideal para piping a jq)
php conversor.php --format=json 100 COP | jq .

# Usando el binario PHAR
./conversor.phar --format=json --providers=open 100 ARS
```


## Author
Victor Manuel Suarez Torres - victormst@gmail.com.
