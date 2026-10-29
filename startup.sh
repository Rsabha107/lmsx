#!/bin/bash
set -euo pipefail

LOG_FILE="/home/logs/startup_$(date '+%Y%m%d_%H%M%S').log"
# QUEUE_LOG="/home/logs/queue.log"
QUEUE_LOG="/home/logs/queue_$(date +'%Y-%m-%d').log"


# Helper function for logging with timestamps
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log "--- STARTUP SCRIPT EXECUTION INITIATED ---"

# Ensure log directory exists
mkdir -p /home/logs

# ----------------------------
# 1. Nginx Setup
# ----------------------------
if [ -f /home/site/wwwroot/default ]; then
    cp /home/site/wwwroot/default /etc/nginx/sites-available/default
    log "Nginx default site config copied."
else
    log "ERROR: /home/site/wwwroot/default not found!"
    exit 1
fi

if [ -f /home/site/wwwroot/hide_tokens.conf ]; then
    cp /home/site/wwwroot/hide_tokens.conf /etc/nginx/conf.d/hide_tokens.conf
    log "Nginx security config copied."
else
    log "WARNING: hide_tokens.conf not found, skipping."
fi

if service nginx reload; then
    log "Nginx service reloaded successfully."
else
    log "ERROR: Failed to reload Nginx!" >&2
    exit 1
fi

# ----------------------------
# 2. Change to Laravel root
# ----------------------------
if [ -d "/home/site/wwwroot" ]; then
    cd /home/site/wwwroot
    log "Changed working directory to: $(pwd)"
else
    log "ERROR: Laravel root /home/site/wwwroot not found!"
    exit 1
fi

# ----------------------------
# 3. Start Laravel Queue Worker
# ----------------------------
# Ensure queue log directory exists
mkdir -p "$(dirname "$QUEUE_LOG")"

nohup php artisan queue:work --sleep=3 --tries=3 >> "$QUEUE_LOG" 2>&1 &
WORKER_PID=$!
log "Laravel queue worker started in background with PID: $WORKER_PID"

# ----------------------------
# 4. Start Laravel Scheduler
# ----------------------------
# Runs routes/console.php: deletions:purge (recycle bin, 03:30), flights:sync, push:job-reminders.
# The loop brings it back if it ever exits; an app restart re-runs this script.
SCHEDULER_LOG="/home/logs/scheduler_$(date +'%Y-%m-%d').log"
nohup bash -c 'while true; do php artisan schedule:work; echo "$(date "+%Y-%m-%d %H:%M:%S") - schedule:work exited, restarting in 10s"; sleep 10; done' >> "$SCHEDULER_LOG" 2>&1 &
SCHEDULER_PID=$!
log "Laravel scheduler started in background with PID: $SCHEDULER_PID (log: $SCHEDULER_LOG)"

log "--- STARTUP SCRIPT EXECUTION COMPLETED ---"
