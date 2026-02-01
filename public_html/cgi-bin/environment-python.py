#!/usr/bin/env python3
import os, json
print("Content-Type: application/json; charset=utf-8")
print()
env = dict(os.environ)
print(json.dumps(env, indent=2))
