// environment-cpp.cpp
#include <iostream>
#include <cstdlib>
int main(){
  std::cout << "Content-Type: text/plain; charset=utf-8\r\n\r\n";
  extern char **environ;
  for(char **env = environ; *env != 0; env++){
    std::cout << *env << "\n";
  }
  return 0;
}
