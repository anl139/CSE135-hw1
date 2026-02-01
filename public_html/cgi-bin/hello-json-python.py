#!/usr/bin/env python3
import os, sys, datetime, cgi, json, html
now = datetime.datetime.utcnow().isoformat()+'Z'
ip = os.environ.get('REMOTE_ADDR','unknown')
form = cgi.FieldStorage()
name = form.getfirst('Lam','Andrew')
message = form.getfirst('message','')
print("Content-Type: application/json; charset=utf-8")
print()
print(json.dumps({
  "greeting": f"Hello from {name}",
  "language": "Python (CGI)",
  "message": message,
  "generated": now,
  "ip": ip
}, indent=2))
