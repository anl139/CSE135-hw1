#!/usr/bin/env python3
import os, sys, cgi, json, datetime

# get form fields
form = cgi.FieldStorage()
method = os.environ.get('REQUEST_METHOD', 'GET')

# support _method override when POST form was used
if method == 'POST' and '_method' in form:
    method = form.getfirst('_method').upper()

# environment info
now = datetime.datetime.utcnow().isoformat()+'Z'
ip = os.environ.get('REMOTE_ADDR','unknown')
host = os.environ.get('HTTP_HOST','')
ua = os.environ.get('HTTP_USER_AGENT','')
content_type = os.environ.get('CONTENT_TYPE','')

data = {}

try:
    if 'application/json' in content_type:
        # read POST body safely
        raw = sys.stdin.read()
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            data = {'raw': raw}
    else:
        # parse standard form fields
        for key in form.keys():
            data[key] = form.getfirst(key)
except Exception as e:
    data = {'error': str(e)}

# build response
resp = {
    'method': method,
    'host': host,
    'time': now,
    'ip': ip,
    'user_agent': ua,
    'content_type': content_type,
    'data': data
}

# output JSON (must be str, not bytes)
print("Content-Type: application/json; charset=utf-8")
print()  # blank line required
print(json.dumps(resp, indent=2))
