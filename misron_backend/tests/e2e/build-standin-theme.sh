#!/usr/bin/env bash
# TEST-ONLY stand-in for the proprietary admin theme (not in the repository): Bootstrap 4.6 + jQuery 3.6 served as the files the admin layout asks for
# (assets/css/vendors.css, assets/js/vendors.js, aiz-core, dashboard). Theme-specific styling is NOT reproduced; this only supplies the
# framework behaviour (grid, .table-responsive, modals) that the funnel admin pages rely on.
#   tests/e2e/build-standin-theme.sh /tmp/standin   then   E2E_THEME_DIR=/tmp/standin php -S 127.0.0.1:8001 -t public tests/e2e/router.php
set -euo pipefail
OUT="${1:?usage: $0 <output dir>}"; mkdir -p "$OUT/assets/css" "$OUT/assets/js" "$OUT/_npm"
( cd "$OUT/_npm" && npm init -y >/dev/null 2>&1 && npm install --no-audit --no-fund bootstrap@4.6.2 jquery@3.6.4 >/dev/null )
cp "$OUT/_npm/node_modules/bootstrap/dist/css/bootstrap.min.css" "$OUT/assets/css/vendors.css"
cat "$OUT/_npm/node_modules/jquery/dist/jquery.min.js" "$OUT/_npm/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" > "$OUT/assets/js/vendors.js"
# the real theme pads its content wrapper; Bootstrap rows use -15px side margins and overhang by 15px without it
printf ".aiz-main-content{padding:0 15px}\n" > "$OUT/assets/css/dashboard.css"
# the layout shows flash messages through the theme's AIZ.plugins.notify(level, message); the stand-in renders them as role=alert elements
: > "$OUT/assets/css/aiz-core.css"
cat > "$OUT/assets/js/aiz-core.js" <<'JS'
window.AIZ = window.AIZ || {}; AIZ.plugins = AIZ.plugins || {};
AIZ.plugins.notify = function (level, message) { var d = document.createElement("div"); d.setAttribute("role", "alert"); d.className = "alert alert-" + level; d.textContent = message; document.body.appendChild(d); };
JS
echo "stand-in theme written to $OUT"
