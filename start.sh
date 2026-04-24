#!/usr/bin/env bash
# SAP Expense Entry System — local / Codespaces launcher
set -euo pipefail

APP_PORT=8000
MOCK_PORT=50001
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[info]${NC}  $*"; }
ok()    { echo -e "${GREEN}[ ok ]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[warn]${NC}  $*"; }
die()   { echo -e "${RED}[fail]${NC}  $*"; exit 1; }

cd "$SCRIPT_DIR"

command -v php >/dev/null || die "PHP not found. Install php-cli."
ok "PHP: $(php --version | head -1)"

mkdir -p config db uploads

cat > config/.env.local <<EOF
SAP_BASE_URL=http://localhost:${MOCK_PORT}/b1s/v1
SAP_COMPANY_DB=MOCK
SAP_USERNAME=manager
SAP_PASSWORD=manager
SAP_VERIFY_SSL=false
DB_DRIVER=sqlite
APP_CURRENCY=EUR
APP_VAT_RATE=19
APP_COMPANY=Demo Company
EOF
ok "Config written (SQLite + SAP mock)"

# Kill anything already on our ports (lsof or fuser fallback)
kill_port() {
    local PORT=$1
    local PID
    if command -v lsof >/dev/null 2>&1; then
        PID=$(lsof -ti ":${PORT}" 2>/dev/null || true)
    elif command -v fuser >/dev/null 2>&1; then
        PID=$(fuser "${PORT}/tcp" 2>/dev/null | tr -d ' ' || true)
    fi
    if [ -n "${PID:-}" ]; then
        warn "Killing stale process on :${PORT} (PID $PID)"
        kill "$PID" 2>/dev/null || true
        sleep 0.5
    fi
}
kill_port "$APP_PORT"
kill_port "$MOCK_PORT"

info "Starting SAP B1 mock on :${MOCK_PORT}…"
php -S "0.0.0.0:${MOCK_PORT}" mock/sap_mock.php >/tmp/sap_mock.log 2>&1 &
MOCK_PID=$!
sleep 1
kill -0 "$MOCK_PID" 2>/dev/null || { warn "SAP mock failed:"; cat /tmp/sap_mock.log; die "Mock server did not start."; }
ok "SAP mock running (PID $MOCK_PID)"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Expense Entry System is ready!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  Local :  ${CYAN}http://localhost:${APP_PORT}${NC}"
echo -e "  CS    :  Check the ${YELLOW}Ports tab${NC} in Codespaces → forward port ${APP_PORT} → set to Public"
echo ""
echo -e "  Default login: ${YELLOW}admin@company.com${NC} / ${YELLOW}admin123${NC}"
echo ""
echo -e "  Press ${YELLOW}Ctrl+C${NC} to stop."
echo ""

trap 'echo ""; info "Stopping…"; kill '"$MOCK_PID"' 2>/dev/null; exit 0' INT TERM

# Bind to 0.0.0.0 so Codespaces port forwarding works
php -S "0.0.0.0:${APP_PORT}" -t "$SCRIPT_DIR" "$SCRIPT_DIR/router.php"
