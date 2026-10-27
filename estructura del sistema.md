# FinControl — Especificación del proyecto

Este archivo es la fuente de verdad para construir la aplicación. Léelo completo antes de escribir código. Si algo no está definido aquí, pregunta antes de asumir.

## 1. Qué es

Aplicación web de control financiero personal basada en **presupuesto por sobres**, con seguimiento de **compromisos** (deudas de tarjeta, cuota del departamento, gastos fijos) y un número central: **cuánto puedo gastar hoy**.

Principios no negociables:
- Registrar un gasto debe tomar menos de 5 segundos (un input de texto: `25 comida`).
- La app informa, no prohíbe. Un sobre en negativo se muestra en rojo, no bloquea.
- Todo lo derivado se calcula, nunca se guarda (gastado, restante, disponible hoy, meses restantes de deuda).
- Montos en `DECIMAL(12,2)`. Nunca float.
- Cada registro pertenece a un `user_id`. Toda consulta filtra por el usuario autenticado, sin excepción.

## 2. Stack

- **Laravel 12**, PHP 8.3+, **MySQL 8**.
- **Blade + Alpine.js** (CDN). **Sin Vite, sin npm, sin build.** Nada de `npm run dev`.
- CSS: **Tailwind por CDN** (`https://cdn.tailwindcss.com`) con config inline mínima. Si prefieres evitar el CDN de Tailwind en producción, usa un `public/css/app.css` escrito a mano; no introduzcas un paso de compilación.
- Gráficos: **Chart.js** por CDN (`https://cdn.jsdelivr.net/npm/chart.js@4.4.1`).
- Auth: **Laravel Fortify** (sin Jetstream, sin Breeze porque Breeze trae Vite). Incluye registro, login, verificación de email, reset de password y 2FA.
- Scheduler de Laravel (`schedule:run` en cron) para recurrencias y recordatorios.
- Notificaciones: email (Mailable) en v1. Canal WhatsApp queda como interfaz vacía para después.
- Tests: Pest.

## 3. Estructura de carpetas

```
app/
  Models/
    User.php
    Period.php             # mes presupuestario
    Income.php
    Commitment.php         # deuda o gasto fijo
    CommitmentPayment.php
    Envelope.php           # sobre
    Movement.php           # gasto registrado en un sobre
    RecurringRule.php      # plantillas de recurrencia
  Services/
    Budget/
      CascadeCalculator.php     # la cascada ingresos → disponible → hoy
      DailyAllowance.php
      PeriodBuilder.php         # arma el mes: copia sobres, genera cuotas
    Parsing/
      QuickEntryParser.php      # "25 comida" → monto + sobre
      YapeNotificationParser.php
    Debts/
      CardCycleCalculator.php   # corte, pago, monto para no generar intereses
      PayoffEstimator.php       # meses restantes
  Http/
    Controllers/
      DashboardController.php   # pantalla Hoy
      PeriodController.php      # armar el mes
      EnvelopeController.php
      MovementController.php
      IncomeController.php
      CommitmentController.php
      CommitmentPaymentController.php
      HistoryController.php
      ChartController.php       # devuelve JSON para Chart.js
      ExportController.php      # JSON / CSV
      ImportController.php
    Requests/                   # FormRequest por acción (validación)
    Middleware/
  Policies/                     # una policy por modelo, todas verifican user_id
  Console/Commands/
    GenerateMonthlyPeriod.php   # día 1: crea periodo, sobres, cuotas pendientes
    SendPaymentReminders.php    # diario: avisos 2 días antes de vencer
    SendMonthlySummary.php      # día 1: resumen del mes cerrado
  Notifications/
    PaymentDueSoon.php
    MonthlySummary.php
database/
  migrations/
  seeders/
    DemoSeeder.php              # datos de ejemplo realistas en soles
  factories/
resources/views/
  layouts/app.blade.php         # layout con nav inferior tipo móvil
  dashboard/today.blade.php
  periods/setup.blade.php
  envelopes/
  commitments/index.blade.php
  commitments/show.blade.php
  history/index.blade.php
  charts/index.blade.php
  components/                   # Blade components: card, progress-bar, money
routes/
  web.php
  console.php                   # schedule
tests/
  Feature/
  Unit/                         # CascadeCalculator, parsers, CardCycleCalculator
public/
  manifest.json                 # PWA
  sw.js                         # service worker mínimo (cache del shell)
  icons/
```

## 4. Esquema de base de datos

Todas las tablas tienen `id` (bigint), `user_id` (FK a users, index), `created_at`, `updated_at`. Soft deletes solo en `movements` y `commitment_payments`.

### users
Lo que trae Fortify + `currency_default` (`PEN`), `usd_rate` DECIMAL(6,3) editable a mano (tipo de cambio manual, sin API).

### periods
| campo | tipo | nota |
|---|---|---|
| year | smallint | |
| month | tinyint | 1-12 |
| expected_income | decimal(12,2) | ingreso proyectado |
| savings_target | decimal(12,2) | ahorro que se separa al inicio |
| closed_at | timestamp null | se cierra al generar el siguiente |

Unique (`user_id`, `year`, `month`).

### incomes
| campo | tipo |
|---|---|
| period_id | FK |
| amount | decimal(12,2) |
| currency | char(3) default PEN |
| source | string |
| received_at | date |
| confirmed | boolean |

`confirmed = false` es ingreso esperado; `true` es ingreso real. La cascada usa confirmados cuando existen, si no, `expected_income` del periodo.

### commitments
| campo | tipo | nota |
|---|---|---|
| name | string | "Depa Los Olivos", "BCP Visa" |
| type | enum(debt, fixed, annual) | deuda / gasto fijo mensual / gasto anual prorrateado |
| total_balance | decimal(12,2) null | saldo pendiente (solo debt) |
| monthly_amount | decimal(12,2) | cuota o gasto mensual; en annual es el total anual ÷ 12 |
| pay_day | tinyint | día del mes de vencimiento |
| cut_day | tinyint null | día de corte (solo tarjetas) |
| interest_rate | decimal(6,3) null | TEA informativa |
| is_card | boolean | si true, los gastos con tarjeta suben `total_balance` |
| active | boolean | |

### commitment_payments
| campo | tipo |
|---|---|
| commitment_id | FK |
| period_id | FK |
| amount | decimal(12,2) |
| paid_at | date |
| note | string null |

Al crear un pago sobre un `commitment` tipo `debt`: `total_balance -= amount`. Al borrar: se revierte.

### envelopes
| campo | tipo |
|---|---|
| period_id | FK |
| name | string |
| allocated | decimal(12,2) |
| sort_order | smallint |
| color | string null |

### movements
| campo | tipo | nota |
|---|---|---|
| envelope_id | FK | |
| amount | decimal(12,2) | |
| spent_at | datetime | default now |
| note | string null | |
| paid_with_commitment_id | FK null | si se pagó con tarjeta, apunta al commitment `is_card` |

Al crear un movement con `paid_with_commitment_id`: suma `amount` a `total_balance` de esa tarjeta. Al borrar: resta. Esto se hace en un Observer, en transacción.

### recurring_rules
| campo | tipo |
|---|---|
| name | string |
| kind | enum(envelope, commitment_due) |
| commitment_id | FK null |
| amount | decimal(12,2) |
| day_of_month | tinyint null |
| active | boolean |

El comando `GenerateMonthlyPeriod` lee estas reglas para armar el mes nuevo.

## 5. La cascada (lógica central)

`CascadeCalculator::for(Period $period): CascadeResult`

```
income        = sum(incomes.confirmed) ?: period.expected_income
savings       = period.savings_target
commitments   = sum(commitments.active.monthly_amount)
paidThisMonth = sum(commitment_payments where period_id)
pendingDue    = commitments - paidThisMonth
availableForEnvelopes = income - savings - commitments
allocated     = sum(envelopes.allocated)
overAllocated = max(0, allocated - availableForEnvelopes)
spent         = sum(movements of period envelopes)
remaining     = allocated - spent
daysLeft      = días que faltan del mes incluyendo hoy (mínimo 1)
todayAllowance = remaining / daysLeft
requiredIncome = savings + commitments + allocated   # "cuánto tengo que ingresar"
incomeGap     = max(0, requiredIncome - income)
```

Por sobre: `spent`, `remaining`, `percent`. Si `remaining < 0` → estado `over`.

`CardCycleCalculator`: dada una tarjeta con `cut_day` y `pay_day`, devuelve el rango del ciclo actual, el monto consumido en ese ciclo (movements con `paid_with_commitment_id` entre fechas), y `amountToAvoidInterest` = consumo del ciclo cerrado más reciente.

`PayoffEstimator`: `ceil(total_balance / monthly_amount)` meses; si hay tasa, versión con interés simple mensual como aproximación informativa.

## 6. Parsers

`QuickEntryParser::parse(string $input, Collection $envelopes): ParsedEntry`
- Extrae el primer número (acepta `25`, `25.50`, `25,50`, `s/25`).
- El resto del texto se compara contra nombres de sobres: match exacto, luego prefijo, luego `similar_text` > 70 %. Case-insensitive, sin tildes.
- Si no hay match devuelve `envelope = null` y la UI muestra botones.
- Una palabra `tc` o `tarjeta` al final marca que se pagó con la tarjeta por defecto del usuario.

`YapeNotificationParser::parse(string $text): ?array`
- Patrones: `Yapeaste S/ 25.00 a Juan Pérez`, `Plin: enviaste S/ 12.50 a ...`. Devuelve `amount` y `counterpart`. Si no matchea, null.

## 7. Pantallas y rutas

Todas bajo middleware `auth`, `verified`. Navegación inferior fija con 5 ítems. Diseño mobile-first, 100 % funcional en escritorio.

| ruta | vista | qué muestra |
|---|---|---|
| `GET /` | dashboard/today | Número grande "Puedes gastar hoy". Fila de compromisos del mes (total, pagado, faltan, próximo vencimiento). Lista de sobres con barra y restante. Input rápido fijo abajo. Si no hay periodo del mes actual → redirige a setup. |
| `POST /movements/quick` | — | Recibe `input`, parsea, crea movement o devuelve JSON con sobres para elegir. Alpine hace fetch y actualiza sin recargar. |
| `GET /periods/setup` | periods/setup | Ingresos esperados, ahorro, lista de compromisos activos (solo lectura con total), sobres editables. La cascada se recalcula en vivo con Alpine mientras escribes. Aviso rojo si `overAllocated > 0` o `incomeGap > 0`. Botón "copiar sobres del mes anterior". |
| `GET /commitments` | commitments/index | Tarjetas con saldo, cuota, progreso, meses restantes, próximo vencimiento. Botón "pagar cuota" (modal). |
| `GET /commitments/{id}` | commitments/show | Detalle, historial de pagos, si es tarjeta: ciclo actual y monto para no generar intereses. |
| `GET /history` | history/index | Lista unificada del mes (movimientos, ingresos, pagos) con filtro por tipo. Editar / eliminar inline. |
| `GET /charts` | charts/index | Tres gráficos (ver sección 8). |
| `GET /charts/data/{type}` | JSON | `envelopes`, `monthly`, `debt` |
| `GET /export`, `POST /import` | — | JSON completo del usuario / CSV de movimientos. |
| `GET /settings` | settings | Tipo de cambio USD, tarjeta por defecto, email de recordatorios. |

Resource routes estándar para `envelopes`, `movements`, `incomes`, `commitments`, `commitment-payments`, `recurring-rules`.

## 8. Gráficos (solo tres)

1. **Dona**: gasto del mes por sobre.
2. **Barras agrupadas** por mes (últimos 6): ingresos, gastos en sobres, pagos de deuda.
3. **Línea**: evolución del `total_balance` sumado de todas las deudas, un punto por mes (snapshot calculado desde pagos y cargos; no se guarda).

Los datos se calculan en `ChartController` con consultas agregadas y se devuelven en JSON. Nada se precalcula.

## 9. Tareas programadas (`routes/console.php`)

- `GenerateMonthlyPeriod` — día 1 a las 00:05. Cierra el periodo anterior, crea el nuevo copiando sobres (misma asignación, gasto en cero; el sobrante **no** se arrastra), aplica `recurring_rules`. Los compromisos `annual` aportan 1/12 al cálculo automáticamente, no necesitan regla.
- `SendPaymentReminders` — diario 08:00. Para cada compromiso activo cuyo `pay_day` sea en 2 días y sin pago registrado este periodo, envía `PaymentDueSoon`. Además el dashboard muestra badge rojo "vence en N días".
- `SendMonthlySummary` — día 1 a las 08:00. Email con: ingresos, gastado, sobres excedidos, cuánto bajó la deuda total, ahorro logrado.

Cron del servidor: `* * * * * php artisan schedule:run`.

## 10. Seguridad

- Policies en todos los modelos; `authorize()` en cada controller. Un usuario jamás puede ver o tocar un `id` ajeno (los tests deben cubrirlo).
- FormRequests con validación estricta de montos (`numeric`, `min:0.01`, `max:999999.99`).
- CSRF en todos los formularios y fetch de Alpine (meta tag + header).
- Rate limit en `/movements/quick` y en login.
- 2FA de Fortify habilitado y recomendado en settings.
- Nunca claves en Blade ni en JS. Variables sensibles solo en `.env`.
- Backups: `spatie/laravel-backup` configurado a disco local + S3 opcional, diario.

## 11. PWA

- `manifest.json` con nombre, iconos, `display: standalone`, `start_url: /`.
- `sw.js` mínimo: cachea layout, CSS, JS de CDN. No cachea respuestas de datos.
- El input rápido tiene `autofocus` al abrir desde la pantalla de inicio.

## 12. Convenciones de código

- Controllers delgados; toda lógica en `Services/`. Un controller no hace cálculos.
- Dinero siempre como string decimal en PHP (`bcmath`) o `brick/money`; nunca operar floats.
- Fechas en zona `America/Lima`.
- Blade components para: `<x-money :amount>` (formatea `S/ 1,250.00`), `<x-progress :percent :state>`, `<x-card>`.
- Alpine: componentes pequeños inline (`x-data`), sin store global salvo el estado del input rápido.
- Nombres en inglés en código, textos de UI en español.
- Commits pequeños por fase.

## 13. Orden de construcción (fases)

Construye en este orden. No avances de fase sin que la anterior tenga sus tests en verde.

1. **Base**: proyecto Laravel 12, MySQL, Fortify, layout con nav, Tailwind CDN, migraciones y modelos con relaciones, policies, DemoSeeder.
2. **Cascada**: `CascadeCalculator`, `DailyAllowance` con tests unitarios exhaustivos (mes con y sin ingresos confirmados, sobre en negativo, último día del mes, periodo vacío).
3. **Hoy + input rápido**: dashboard, `QuickEntryParser` con tests, endpoint quick, Alpine sin recarga.
4. **Armar el mes**: setup con cascada en vivo, copiar sobres del mes anterior.
5. **Compromisos**: CRUD, pagos, observer de saldo, `CardCycleCalculator`, `PayoffEstimator`, pantalla de detalle.
6. **Historial** con edición y borrado (con reversión de saldos).
7. **Gráficos**: endpoints JSON + Chart.js.
8. **Automatización**: comandos programados, notificaciones por email, `recurring_rules`.
9. **Export / import**, settings, parser de Yape, PWA.
10. **Hardening**: rate limits, backups, tests de aislamiento entre usuarios, revisión de N+1.

## 14. Datos del seeder de demo

Un usuario `demo@fincontrol.test / password`, periodo actual con:
- Ingreso esperado S/ 6,500; ahorro S/ 800.
- Compromisos: "Departamento" (debt, saldo 85,000, cuota 1,450, día 5), "BCP Visa" (debt, is_card, saldo 2,300, cuota 450, corte 20, pago 5), "Internet" (fixed, 120, día 15), "Seguro auto" (annual, 1,800/12).
- Sobres: Comida fuera 600, Supermercado 700, Salidas 400, Transporte 250, Antojos 150, Varios 300.
- 25 movimientos distribuidos en el mes, 3 pagados con la tarjeta.
- 5 meses anteriores con datos para que los gráficos tengan historia.
