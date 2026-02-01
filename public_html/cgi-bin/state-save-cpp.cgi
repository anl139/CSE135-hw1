// state-save-cpp.cgi
#include <iostream>
#include <cstdlib>
#include <string>
#include <fstream>
#include <unistd.h>

std::string get_env(const char* k){
  const char* v = std::getenv(k);
  return v ? v : "";
}

int main(){
  std::string cookie = get_env("HTTP_COOKIE");
  std::string sid;

  // extract SID
  size_t p = cookie.find("SID=");
  if(p != std::string::npos){
    size_t e = cookie.find(';', p);
    sid = cookie.substr(p+4, e - (p+4));
  } else {
    char tmp[] = "/tmp/sidXXXXXX";
    int fd = mkstemp(tmp);
    close(fd);
    sid = tmp + 5;
    std::cout << "Set-Cookie: SID=" << sid << "; Path=/\r\n";
  }

  int len = atoi(get_env("CONTENT_LENGTH").c_str());
  std::string body(len, '\0');
  std::cin.read(&body[0], len);

  std::ofstream ofs("/tmp/session_" + sid);
  ofs << body;
  ofs.close();

  std::cout << "Status: 303 See Other\r\n";
  std::cout << "Location: /cgi-bin/state-view-cpp.cgi\r\n\r\n";
  return 0;
}
