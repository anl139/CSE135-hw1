#!/usr/bin/env python3
# hello-html-python.py
import os, sys, datetime, cgi

now = datetime.datetime.utcnow().isoformat() + 'Z'
ip = os.environ.get('REMOTE_ADDR', 'unknown')
form = cgi.FieldStorage()
name = form.getfirst('name', 'Team')
message = form.getfirst('message', '')

print("Content-Type: text/html; charset=utf-8")
print()
print("<!doctype html><html><head><meta charset='utf-8'><title>Hello (Python)</title></head><body>")
print(f"<h1>Hello from {cgi.escape(name)}</h1>")
print(f"<p>{cgi.escape(message)}</p>")
print(f"<p><strong>Generated:</strong> {now}</p>")
print(f"<p><strong>Your IP:</strong> {ip}</p>")
print("</body></html>")
