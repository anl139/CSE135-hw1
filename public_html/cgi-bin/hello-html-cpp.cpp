// hello-html-cpp.cpp
#include <iostream>
#include <cstdlib>
#include <ctime>
int main(){
  std::time_t t = std::time(nullptr);
  char buf[100];
  std::strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%SZ", std::gmtime(&t));
  const char* ip = std::getenv("REMOTE_ADDR");
  if(!ip) ip = "unknown";
  std::cout << "Content-Type: text/html; charset=utf-8\r\n\r\n";
  std::cout << "<!doctype html><html><head><meta charset='utf-8'><title>Hello C++</title></head><body>\n";
  std::cout << "<h1>Hello Andrew Lam</h1>\n";
  std::cout << "<p>Generated: " << buf << "</p>\n";
  std::cout << "<p>Your IP: " << ip << "</p>\n";
  std::cout << "</body></html>\n";
  return 0;
}
