#!/usr/bin/env bash
set -euo pipefail

SSH_HOST="${HELIOS_TUNNEL_SSH:-forge@159.89.30.45}"
REMOTE="${HELIOS_TUNNEL_REMOTE:-185.67.190.62:1433}"
LOCAL_PORT="${HELIOS_TUNNEL_LOCAL_PORT:-14332}"
SPEC="127.0.0.1:${LOCAL_PORT}:${REMOTE}"

if [[ -f /.dockerenv ]]; then
    echo "Spusť na hostiteli, ne v kontejneru." >&2
    exit 2
fi

pid() { pgrep -f "ssh -f -N.*-L ${SPEC}" | head -1 || true; }

case "${1:-status}" in
    start)
        if [[ -n "$(pid)" ]]; then echo "běží (PID $(pid))"; exit 0; fi
        ssh -f -N -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 -o ServerAliveCountMax=3 -L "$SPEC" "$SSH_HOST"
        echo "spuštěn (PID $(pid)), Helios na 127.0.0.1:${LOCAL_PORT}"
        ;;
    stop)
        if [[ -n "$(pid)" ]]; then kill "$(pid)"; echo "zastaven"; else echo "neběží"; fi
        ;;
    status)
        if [[ -n "$(pid)" ]]; then echo "běží (PID $(pid))"; else echo "neběží"; fi
        ;;
    *)
        echo "Použití: $0 start|stop|status" >&2
        exit 2
        ;;
esac
