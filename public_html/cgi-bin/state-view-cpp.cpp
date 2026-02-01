// state-view-cpp.cgi
#include <iostream>
#include <cstdlib>
#include <string>
#include <fstream>
#include <sstream>

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
  }

  std::cout << "Content-Type: text/html; charset=utf-8\r\n\r\n";
  std::cout << "<html><body>";
  std::cout << "<h1>Saved State (C++)</h1>";

  if(!sid.empty()){
    std::ifstream ifs("/tmp/session_" + sid);
    if(ifs.good()){
      std::ostringstream ss;
      ss << ifs.rdbuf();
      std::cout << "<pre>" << ss.str() << "</pre>";
    } else {
      std::cout << "<p>No saved data.</p>";
    }
  } else {
    std::cout << "<p>No session.</p>";
  }

  std::cout << "<p><a href='/state-form.html'>Edit</a></p>";
  std::cout << "<p><a href='/cgi-bin/state-clear-cpp.cgi'>Clear</a></p>";
  std::cout << "</body></html>";
  return 0;
}
