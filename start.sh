#!/usr/bin/env bash
# ─── SAP Expense Entry System — zero-dependency local launcher ───────────────
# Requirements: PHP 8+ (php -S)  — no MySQL, no Docker needed
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

# ─── 1. PHP check ─────────────────────────────────────────────────────────────
command -v php >/dev/null || die "PHP not found. Install php-cli."
ok "PHP: $(php --version | head -1)"

# ─── 2. Write local env (SQLite, no MySQL needed) ─────────────────────────────
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
ok "Config written (SQLite, SAP mock on :${MOCK_PORT})"

# ─── 3. Kill any stale processes on our ports ─────────────────────────────────
for PORT in $APP_PORT $MOCK_PORT; do
    PID=$(lsof -ti ":${PORT}" 2>/dev/null || true)
    [ -n "$PID" ] && { warn "Killing stale process on :${PORT} (PID $PID)"; kill "$PID" 2>/dev/null || true; sleep 0.5; }
done

# ─── 4. Start SAP B1 mock ─────────────────────────────────────────────────────
info "Starting SAP B1 mock on :${MOCK_PORT}…"
php -S "localhost:${MOCK_PORT}" mock/sap_mock.php >/tmp/sap_mock.log 2>&1 &
MOCK_PID=$!
sleep 1
kill -0 "$MOCK_PID" 2>/dev/null || die "SAP mock failed. See /tmp/sap_mock.log"
ok "SAP mock running (PID $MOCK_PID)"

# ─── 5. Print summary and start app server ────────────────────────────────────
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Expense Entry System is ready!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  ${CYAN}http://localhost:${APP_PORT}${NC}   ← open this in your browser"
echo ""
echo -e "  SAP mock  : http://localhost:${MOCK_PORT}/b1s/v1"
echo -e "  Database  : db/expense_app.sqlite  (auto-created)"
echo -e "  Receipts  : uploads/"
echo ""
echo -e "  Press ${YELLOW}Ctrl+C${NC} to stop."
echo ""

trap 'echo ""; info "Stopping…"; kill '"$MOCK_PID"' 2>/dev/null; exit 0' INT TERM

php -S "localhost:${APP_PORT}" -t "$SCRIPT_DIR" "$SCRIPT_DIR/router.php"
