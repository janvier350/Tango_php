# Esquema MySQL de TANGO (para el DER en MySQL Workbench)

Scripts para crear la **estructura completa y actual** de la base de datos de
TANGO (solo estructura, sin datos) y generar el **diagrama entidad-relación
(DER)** con MySQL Workbench.

> El proyecto usa PHP + MySQLi sobre una base ya existente: **no hay
> migraciones ni ORM**. El esquema se reconstruyó leyendo las consultas del
> código (`INSERT`/`SELECT`/`UPDATE`) y los `CREATE TABLE` que el propio código
> ejecuta (`audit.php`). Por eso los **tipos y longitudes son inferidos**; ver
> las notas de conversión y los supuestos más abajo.

## Archivos

| Archivo | Qué hace |
|---|---|
| `Tango_mysql_01_estructura.sql` | Crea la base `tango` y **todas las tablas** (PK, índices, defaults, un CHECK). **No** crea llaves foráneas. |
| `Tango_mysql_02_relaciones_logicas.sql` | Agrega las **llaves foráneas lógicas** (las relaciones que el código implica por nombre/uso, que la base real no declara). Cada una es un `ALTER TABLE` independiente y se puede omitir. |

## Requisitos
- **MySQL 8.0 o superior.** (Probado en MySQL **8.0.46**.)
- MySQL Workbench 8.x.

## Cómo ejecutarlo y sacar el DER en Workbench

1. Abre MySQL Workbench y conéctate a tu servidor MySQL 8.
2. **File → Open SQL Script…** y abre `Tango_mysql_01_estructura.sql`.
3. Ejecútalo con el **primer rayo** (⚡ *Execute the selected portion…*, o `Ctrl+Shift+Enter`).
4. Abre y ejecuta igual `Tango_mysql_02_relaciones_logicas.sql`
   (necesario para que el diagrama dibuje las relaciones).
   - *Opcional:* si no quieres alguna relación en el diagrama, borra o comenta
     ese `ALTER TABLE` antes de ejecutar.
5. Genera el DER: **Database → Reverse Engineer…** (`Ctrl+R`), elige la conexión,
   selecciona el esquema **`tango`**, siguiente hasta el final. Workbench dibuja
   el diagrama con todas las tablas y las relaciones.
   - Ajusta la distribución con **Arrange → Autolayout** si lo necesitas.

> Orden importante: **01 antes que 02**. El script 02 asume el esquema recién
> creado y **vacío** (algunos centinela reales como `movimientos.ID_PROYECTO = 0`
> no cumplirían la FK si hubiera datos; para el DER no importa).

## Notas de conversión / supuestos

- **Sin DDL de origen:** los tipos se eligieron por criterio a partir del uso:
  - IDs → `INT` (el `id` de `auditoria` es `BIGINT`). Todas las llaves (PK y FK)
    son `INT` del **mismo tipo y signo** para que las FK no fallen.
  - Montos → `DECIMAL(12,2)` (no `FLOAT`).
  - Fechas sin hora → `DATE`; con hora → `DATETIME`.
  - Texto largo → `TEXT`; textos cortos → `VARCHAR` (longitudes estimadas).
  - Booleanos → `TINYINT(1)` (`ES_INDEPENDIENTE`, `FORM_SIMPLE`, `activo`).
- **Motor** InnoDB, charset **utf8mb4 / utf8mb4_unicode_ci** en todas las tablas.
- **PRIMARY KEY** en las 15 tablas (ninguna quedó sin PK).
- **UNIQUE** solo donde el código garantiza unicidad: `usuarios.usuario` y
  `glosario_tokens.token`. En otros catálogos (empresas, bancos, proyectos,
  beneficiarios) la unicidad se valida por aplicación; se dejaron como índice
  simple para no romper si la base real tuviera duplicados.
- **CHECK** `chk_mov_estado` (`ESTADO IN ('A','I')`): inferido del uso, no del DDL.
- **Columnas legado** en `movimientos` (`tipo`, `categoria`, `monto`,
  `observaciones`): se conservan porque `index.php` (saldo contable del sistema)
  y `vales.php` todavía las consultan.
- **Tabla legado** `vales_caja`: solo la usa `vales.php`, que no está enlazada en
  ningún menú. Se incluye para que el DER refleje la base real; **verificar si
  sigue en uso**.
- **Tablas excluidas** (de otro proyecto, no de TANGO): `ADM_USUARIO` y
  `ADM_ROL`. Solo las usa `class/checkLogin.php` (un login de una app "TodoList")
  que **no se incluye en ninguna página de TANGO**.

## Relaciones (13 FK lógicas en el script 02)

- `usuarios.ID_ROL` → `CTR_ROL.ID_ROL`
- `usuarios.ID_CTR_OFICINA` → `CTR_OFICINA.ID_OFICINA`
- `CAT_REPOSICION.ID_CAT_REPOCICIONES` → `cat_reposiciones.id`
- `movimientos.ID_OFICINA` → `CTR_OFICINA.ID_OFICINA`
- `movimientos.ID_USUARIO` → `usuarios.id`
- `movimientos.ID_USUARIO_REVISA` → `usuarios.id`
- `movimientos.inf_fin` → `CAT_REPOSICION.ID_REPOSICION`
- `movimientos.ID_PROYECTO` → `PROYECTOS.ID_PROYECTO` *(solo DER: usa 0 = sin proyecto)*
- `arqueos.ID_OFICINA` → `CTR_OFICINA.ID_OFICINA`
- `arqueos.ID_USUARIO` → `usuarios.id`
- `BENEFICIARIO.ID_USUARIO_CREA` → `usuarios.id`
- `glosario_tokens.creado_por` → `usuarios.id`
- `auditoria.id_usuario` → `usuarios.id` *(histórico)*

## Prueba realizada (MySQL 8.0.46)

Ambos scripts se ejecutaron **sin errores** (sin `--force`). Verificado con
`information_schema`: **15 tablas, 13 FK, 1 CHECK, 15 PK, 0 tablas sin PK**.
Pruebas de inserción: `AUTO_INCREMENT` y valores por defecto OK; violación de
**CHECK** rechazada (ERROR 3819) y violación de **FK** rechazada (ERROR 1452).
