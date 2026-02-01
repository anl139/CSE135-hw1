#!/usr/bin/env python3
import os, sys, json, http.cookies, datetime, cgi, uuid

SESSION_DIR = '/tmp/simple_sessions'
os.makedirs(SESSION_DIR, exist_ok=True)

def load_session(sid):
    path = os.path.join(SESSION_DIR, sid)
    if os.path.exists(path):
        try:
            return json.load(open(path))
        except:
            return {}
    return {}

def save_session(sid, data):
    path = os.path.join(SESSION_DIR, sid)
    json.dump(data, open(path, 'w'))

# cookie handling
cookie = http.cookies.SimpleCookie(os.environ.get('HTTP_COOKIE',''))
sid = cookie.get('SID')
if not sid:
    sid = http.cookies.SimpleCookie()
    sid_val = str(uuid.uuid4())
    cookie_out = http.cookies.SimpleCookie()
    cookie_out['SID'] = sid_val
    cookie_out['SID']['path'] = '/'
    print("Set-Cookie: " + cookie_out.output().split(': ',1)[1])
    sid = type('X',(object,),{'value':sid_val})()

sid_val = sid.value
session = load_session(sid_val)

form = cgi.FieldStorage()
action = form.getfirst('action','view')

if action=='set' and os.environ.get('REQUEST_METHOD','GET')=='POST':
    session['saved'] = {'name': form.getfirst('name',''), 'message': form.getfirst('message',''), 'time': datetime.datetime.utcnow().isoformat()+'Z'}
    save_session(sid_val, session)
    print("Content-Type: text/html; charset=utf-8")
    print()
    print("<html><body>Saved. <a href='state-python.py'>view</a></body></html>")
    sys.exit()

if action=='clear':
    session.pop('saved', None)
    save_session(sid_val, session)
    print("Content-Type: text/html; charset=utf-8")
    print()
    print("<html><body>Cleared. <a href='state-python.py'>view</a></body></html>")
    sys.exit()

# view page
print("Content-Type: text/html; charset=utf-8")
print()
print("<html><body>")
print("<h1>State (Python CGI)</h1>")
print("<form method='post' action='state-python.py'>")
print("<input type='hidden' name='action' value='set' />")
print("Name: <input name='name' /><br>")
print("Message: <input name='message' /><br>")
print("<button type='submit'>Save</button></form>")
print("<p><a href='state-python.py?action=clear'>Clear</a></p>")
print("<h2>Saved</h2>")
print("<pre>%s</pre>" % (json.dumps(session.get('saved'), indent=2) if session.get('saved') else "No saved data"))
print("</body></html>")
