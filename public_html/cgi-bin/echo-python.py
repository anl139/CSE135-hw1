#!/usr/bin/env python3
import os, sys, json, datetime
from urllib.parse import parse_qs

method = os.environ.get('REQUEST_METHOD', 'GET')
raw_body = ''
if method in ['POST', 'PUT', 'DELETE']:
    raw_body = sys.stdin.read()

content_type = os.environ.get('CONTENT_TYPE','').lower()
data = {}
content_format = 'none'  # default
if method == 'GET':
    qs = os.environ.get('QUERY_STRING','')
    data = {k: v[0] for k,v in parse_qs(qs).items()}
    content_format = 'query'
elif 'application/json' in content_type:
    try:
        data = json.loads(raw_body)
    except json.JSONDecodeError:
        data = {"raw": raw_body}
    content_format = 'json'
elif 'application/x-www-form-urlencoded' in content_type:
    data = {k: v[0] for k,v in parse_qs(raw_body).items()}
    content_format = 'www-form'
else:
    # unknown or other content types
    data = {"raw": raw_body}
    content_format = 'other'

now = datetime.datetime.utcnow().isoformat()+'Z'
resp = {
    "method": method,
    "host": os.environ.get('HTTP_HOST',''),
    "time": now,
    "ip": os.environ.get('REMOTE_ADDR','unknown'),
    "user_agent": os.environ.get('HTTP_USER_AGENT',''),
    "content_type": content_type,
    "format": content_format,
    "data": data,
    "raw_body": raw_body
}

print("Content-Type: application/json; charset=utf-8")
print()
print(json.dumps(resp, indent=2))


