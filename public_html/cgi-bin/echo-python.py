#!/usr/bin/env python3
import os, sys, cgi, json, datetime
form = cgi.FieldStorage()
method = os.environ.get('REQUEST_METHOD','GET')
# support _method override when POST form was used
if method == 'POST' and '_method' in form:
    method = form.getfirst('_method').upper()

now = datetime.datetime.utcnow().isoformat()+'Z'
ip = os.environ.get('REMOTE_ADDR','unknown')
host = os.environ.get('HTTP_HOST','')
ua = os.environ.get('HTTP_USER_AGENT','')
content_type = os.environ.get('CONTENT_TYPE','')

data = {}
if 'application/json' in content_type:
    raw = sys.stdin.read()
    try:
        data = json.loads(raw)
    except:
        data = {'raw': raw}
else:
    # parse fields
    for k in form.keys():
        data[k] = form.getfirst(k)

resp = {
  'method': method,
  'host': host,
  'time': now,
  'ip': ip,
  'user_agent': ua,
  'content_type': content_type,
  'data': data
}
print("Content-Type: application/json; charset=utf-8")
print()
print(json.dumps(resp, indent=2))
