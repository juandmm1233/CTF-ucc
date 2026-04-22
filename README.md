# CTF UCC Demo ver 0.5

Bienvenido al laboratorio CTF de la Universidad Cooperativa de Colombia sede Ibagué.
Este repositorio contiene **dos servicios cloud vulnerables** (IDS y UWS) y un **scoreboard centralizado**, pensados para una clase de 30 estudiantes en formato **Equipo IDS (VM1) vs Equipo UWS (VM2)** durante 3 horas.

## Modelo del juego: Attack & Defend simultaneo

A diferencia de un Red vs Blue tradicional, **cada equipo es full-stack**: opera y defiende
**su propia VM** mientras al mismo tiempo **ataca la VM del rival**.

| Equipo     | Defiende             | Ataca                        | Puerto web | SSH     |
|------------|----------------------|------------------------------|------------|---------|
| Equipo IDS | VM1 — IDS            | VM2 — UWS (`<IP-VM2>:8081`)  | `8080`     | `2222`  |
| Equipo UWS | VM2 — UWS            | VM1 — IDS (`<IP-VM1>:8080`)  | `8081`     | `2223`  |
| —          | Scoreboard (VM3)     | —                            | `9000`     | —       |

> En el scoreboard, el campo `team` espera los valores `ids` o `uws` (minusculas).

### Objetivos (los 3 obligatorios)

1. **Tu servicio web debe seguir respondiendo** durante toda la duracion del ejercicio.
2. **Conseguir banderas del rival** (16 banderas reales en juego: 8 por VM) y/o **acumular mas puntos** que el rival al cierre.
3. **Si tu servicio queda caido > 10 minutos consecutivos: derrota automatica por knock-out.**

### Objetivos sugeridos (extras)

| Concepto                                                | Efecto       |
|---------------------------------------------------------|--------------|
| Cada bandera real del rival enviada                     | +50 a +150   |
| Bandera honeypot enviada (`FLAG{HONEYPOT_*}` / `FLAG{FAKE_*}`) | -50    |
| Reportar honeypot al instructor sin enviarlo            | +25          |
| Sin downtime las 3 horas                                | +100 al cierre |
| Cada 5 min consecutivos de downtime                     | -25          |
| Informe final tecnico (1 pagina por equipo)             | +50          |
| Explicar oralmente *por que* cada parche aplicado mitiga | +25         |

---

## Por donde empezar segun tu rol

| Quien sos                  | Que leer                                           |
|----------------------------|----------------------------------------------------|
| Instructor / encargado     | [`README_INSTRUCTOR.md`](README_INSTRUCTOR.md) — manual completo (despliegue, banderas, mitigaciones, scoreboard) |
| Estudiante del Equipo IDS  | [`ctf1/README.md`](ctf1/README.md) — manual unico del Equipo IDS (defensa IDS + ataque UWS) |
| Estudiante del Equipo UWS  | [`ctf2/README.md`](ctf2/README.md) — manual unico del Equipo UWS (defensa UWS + ataque IDS) |
| Quien quiera material extra| [`docs/RED_TEAM_PLAYBOOK.md`](docs/RED_TEAM_PLAYBOOK.md) y [`docs/BLUE_TEAM_PLAYBOOK.md`](docs/BLUE_TEAM_PLAYBOOK.md) — referencia tecnica detallada |

> Cada equipo tiene **un solo README** dentro de su carpeta `ctf1/` o `ctf2/`. Apenas
> entran por SSH a su VM asignada, ese archivo es el que hay que leer (consolida defensa,
> ofensiva, reglas, despliegue y entregables).

---

## Reglas comunes a ambos equipos

- No apagar Apache, MariaDB ni SSH de **tu propia VM**.
- No borrar archivos que rompan rutas (`index.php`, `admin.php`, etc.). Modificar si.
- No DoS / flood al rival. La idea es ganar **banderas**, no tirarles el server.
- No atacar nada fuera del scope (otras IPs, internet, infra de la universidad).
- Cada bandera cuenta **una sola vez** por equipo en el scoreboard.

---

## Estructura del repo

```
.
├── README.md                  ← este indice
├── README_INSTRUCTOR.md       ← manual completo del lab (instructor)
├── docs/
│   ├── RED_TEAM_PLAYBOOK.md   ← referencia tecnica ofensiva (detalle por vector)
│   ├── BLUE_TEAM_PLAYBOOK.md  ← referencia tecnica defensiva (detalle por vector)
│   └── csrf_demo/             ← template HTML para el vector CSRF
├── ctf1/                      ← VM del Equipo IDS
│   ├── README.md              ← MANUAL UNICO del Equipo IDS
│   ├── docker-compose.yml
│   ├── servidor_web/
│   └── mysql_ssh/
├── ctf2/                      ← VM del Equipo UWS
│   ├── README.md              ← MANUAL UNICO del Equipo UWS
│   ├── docker-compose.yml
│   ├── servidor_web/
│   └── mysql_ssh/
├── scoreboard/                ← Scoreboard (VM3, una sola instancia en la red)
│   ├── docker-compose.yml
│   ├── app.py
│   └── ...
└── docker-compose.yml         ← all-in-one para pruebas locales del instructor
```

---

## Notas operativas

- **Scoreboard:** debe haber **UNA sola instancia** en la red de clase (VM3).
  Antes de levantar cada lab, exportar `SCOREBOARD_URL=http://<IP-SCOREBOARD>:9000`
  para que el dashboard del lab muestre el banner con el link.
- **Health check de servicios:** el instructor monitorea HTTP 200 sobre `/` de cada VM
  cada 30s (recomendado: `watch -n 30 "curl -sI http://<IP-VM>:<PORT> | head -n1"`).
  Acumulado >10 min de fallos = knock-out.
- **Reproducibilidad:** los repos `ctf1/` y `ctf2/` son **auto-contenidos**. Cada equipo
  puede clonar solo su carpeta a la VM y trabajar.
- Para detalles de despliegue, banderas y mitigaciones ver [`README_INSTRUCTOR.md`](README_INSTRUCTOR.md).
