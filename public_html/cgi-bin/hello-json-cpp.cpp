#include <iostream>
#include <cstdlib>
#include <ctime>

int main() {
    std::time_t t = std::time(nullptr);
    char buf[100];
    std::strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%SZ", std::gmtime(&t));

    const char* ip = std::getenv("REMOTE_ADDR");
    if (!ip) ip = "unknown";

    std::cout << "Content-Type: application/json; charset=utf-8\r\n\r\n";
    std::cout << "{\n";
    std::cout << "  \"greeting\": \"Hello from Team Andrew\",\n";
    std::cout << "  \"language\": \"C/C++ (CGI)\",\n";
    std::cout << "  \"generated\": \"" << buf << "\",\n";
    std::cout << "  \"ip\": \"" << ip << "\"\n";
    std::cout << "}\n";
    return 0;
}
