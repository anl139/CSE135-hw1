#!/usr/bin/env python3
import os, sys, json, datetime

method = os.environ.get('REQUEST_METHOD', 'GET')

# _method override for POST
from urllib.parse import parse_qs
if method == 'POST':
    qs = sys.stdin.read()  # always str
    # try to parse JSON
    content_type = os.environ.get('CONTENT_TYPE','')
    if 'application/json' in content_type:
        try:
            data = json.loads(qs)
        except json.JSONDecodeError:
            data = {"raw": qs}
    else:
        # parse as form-urlencoded
        data = {k: v[0] for k,v in parse_qs(qs).items()}
else:
    # GET query string
    data = {k: v[0] for k,v in parse_qs(os.environ.get('QUERY_STRING','')).items()}

now = datetime.datetime.utcnow().isoformat()+'Z'
ip = os.environ.get('REMOTE_ADDR','unknown')
host = os.environ.get('HTTP_HOST','')
ua = os.environ.get('HTTP_USER_AGENT','')

resp = {
    "method": method,
    "host": host,
    "time": now,
    "ip": ip,
    "user_agent": ua,
    "data": data
}

# output JSON
print("Content-Type: application/json; charset=utf-8")
print()
print(json.dumps(resp, indent=2))

