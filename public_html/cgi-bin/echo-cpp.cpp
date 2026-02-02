// echo-cpp.cpp
#include <iostream>
#include <cstdlib>
#include <string>
#include <sstream>
#include <map>
#include <ctime>

/* ---------------- Helper functions ---------------- */

// Decode URL-encoded strings (for form data)
std::string url_decode(const std::string& s) {
    std::string out;
    for (size_t i = 0; i < s.length(); i++) {
        if (s[i] == '+') {
            out += ' ';
        } else if (s[i] == '%' && i + 2 < s.length()) {
            std::string hex = s.substr(i + 1, 2);
            out += static_cast<char>(std::strtol(hex.c_str(), nullptr, 16));
            i += 2;
        } else {
            out += s[i];
        }
    }
    return out;
}

// Parse application/x-www-form-urlencoded body
std::map<std::string, std::string> parse_form(const std::string& body) {
    std::map<std::string, std::string> data;
    std::stringstream ss(body);
    std::string pair;

    while (std::getline(ss, pair, '&')) {
        size_t eq = pair.find('=');
        if (eq != std::string::npos) {
            std::string key = url_decode(pair.substr(0, eq));
            std::string val = url_decode(pair.substr(eq + 1));
            data[key] = val;
        }
    }
    return data;
}

// Escape strings for safe JSON output
std::string json_escape(const std::string& s) {
    std::ostringstream o;
    for (char c : s) {
        switch (c) {
            case '"':  o << "\\\""; break;
            case '\\': o << "\\\\"; break;
            case '\n': o << "\\n";  break;
            case '\r': o << "\\r";  break;
            case '\t': o << "\\t";  break;
            default:   o << c;
        }
    }
    return o.str();
}

/* ---------------- Main CGI program ---------------- */

int main() {
    const char* method       = std::getenv("REQUEST_METHOD");
    const char* content_type = std::getenv("CONTENT_TYPE");
    const char* host         = std::getenv("HTTP_HOST");
    const char* ua           = std::getenv("HTTP_USER_AGENT");
    const char* ip           = std::getenv("REMOTE_ADDR");

    if (!method) method = "GET";
    if (!content_type) content_type = "";
    if (!ip) ip = "unknown";

    // Read request body (if present)
    std::string body;
    const char* len_s = std::getenv("CONTENT_LENGTH");
    if (len_s) {
        int len = std::atoi(len_s);
        body.resize(len);
        std::cin.read(&body[0], len);
    }

    // Parsed data
    std::map<std::string, std::string> data;

    if (std::string(content_type).find("application/x-www-form-urlencoded") != std::string::npos) {
        data = parse_form(body);
    }
    else if (std::string(content_type).find("application/json") != std::string::npos) {
        // No JSON parser used (acceptable for HW)
        data["__json"] = body;
    }

    // Timestamp
    std::time_t t = std::time(nullptr);
    char timebuf[64];
    std::strftime(timebuf, sizeof(timebuf),
                  "%Y-%m-%dT%H:%M:%SZ",
                  std::gmtime(&t));

    // Output response
    std::cout << "Content-Type: application/json; charset=utf-8\r\n\r\n";
    std::cout << "{\n";
    std::cout << "  \"method\": \"" << method << "\",\n";
    std::cout << "  \"host\": \"" << (host ? host : "") << "\",\n";
    std::cout << "  \"time\": \"" << timebuf << "\",\n";
    std::cout << "  \"ip\": \"" << ip << "\",\n";
    std::cout << "  \"user_agent\": \"" << json_escape(ua ? ua : "") << "\",\n";
    std::cout << "  \"content_type\": \"" << content_type << "\",\n";
    std::cout << "  \"raw_body\": \"" << json_escape(body) << "\",\n";
    std::cout << "  \"data\": {\n";

    bool first = true;
    for (const auto& kv : data) {
        if (!first) std::cout << ",\n";
        first = false;
        std::cout << "    \"" << json_escape(kv.first) << "\": \""
                  << json_escape(kv.second) << "\"";
    }

    std::cout << "\n  }\n";
    std::cout << "}\n";

    return 0;
