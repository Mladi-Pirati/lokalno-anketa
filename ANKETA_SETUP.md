# Lokalna anketa

Laravel + Blade app. A visitor picks a **statistical region on an interactive map**,
the map zooms into that region, they pick their **municipality**, then fill in a
survey whose questions are fully **data-driven and extensible**. Responses are
stored in MySQL. Styling uses **Tailwind CSS v4** (via Vite).

## Run it

It's ran in docker so all you can do is run docker compose up.

### Environemnt variables

`KEYCLOAK_BASE_URL=https://keycloak.example.org
KEYCLOAK_REALM=pirati
KEYCLOAK_CLIENT_ID=
KEYCLOAK_CLIENT_SECRET=
KEYCLOAK_REDIRECT_URI="${APP_URL}/auth/keycloak/callback"`

`DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
DB_ROOT_PASSWORD=`