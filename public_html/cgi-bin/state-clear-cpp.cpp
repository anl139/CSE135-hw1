// state-clear-cpp.cgi
#include <iostream>
#include <cstdlib>
#include <string>
#include <cstdio>

std::string get_env(const char* k){
  const char* v = std::getenv(k);
  return v ? v : "";
}

int main(){
  std::string cookie = get_env("HTTP_COOKIE");
  std::string sid;

  size_t p = cookie.find("SID=");
  if(p != std::string::npos){
    size_t e = cookie.find(';', p);
    sid = cookie.substr(p+4, e - (p+4));
    std::remove(("/tmp/session_" + sid).c_str());
  }

  std::cout << "Status: 303 See Other\r\n";
  std::cout << "Location: /cgi-bin/state-view-cpp.cgi\r\n\r\n";
  return 0;
}
