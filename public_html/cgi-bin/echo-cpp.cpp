#include <iostream>
#include <cstdlib>
#include <string>
#include <sstream>
#include <map>
#include <ctime>

/* ---------------- Helper ---------------- */
auto url_decode = [](const std::string& s) {
    std::string out;
    for (size_t i = 0; i < s.size(); ++i) {
        if (s[i] == '+') out += ' ';
        else if (s[i] == '%' && i + 2 < s.size())
            out += static_cast<char>(std::strtol(s.substr(i+1,2).c_str(), nullptr, 16)), i+=2;
        else out += s[i];
    }
    return out;
};

auto parse_form = [&](const std::string& body) {
    std::map<std::string,std::string> data;
    std::stringstream ss(body); std::string pair;
    while (std::getline(ss,pair,'&')) {
        auto eq = pair.find('=');
        if (eq != std::string::npos)
            data[url_decode(pair.substr(0,eq))] = url_decode(pair.substr(eq+1));
    }
    return data;
};

auto json_escape = [](const std::string& s) {
    std::ostringstream o;
    for (char c : s) switch(c){
        case '"': o<<"\\\""; break;
        case '\\': o<<"\\\\"; break;
        case '\n': o<<"\\n"; break;
        case '\r': o<<"\\r"; break;
        case '\t': o<<"\\t"; break;
        default: o<<c;
    }
    return o.str();
};

/* ---------------- Main ---------------- */
int main() {
    const char* method = std::getenv("REQUEST_METHOD") ?: "GET";
    const char* content_type = std::getenv("CONTENT_TYPE") ?: "";
    const char* host = std::getenv("HTTP_HOST") ?: "";
    const char* ua = std::getenv("HTTP_USER_AGENT") ?: "";
    const char* ip = std::getenv("REMOTE_ADDR") ?: "unknown";

    // Read request body
    std::string body;
    if (const char* len_s = std::getenv("CONTENT_LENGTH")) {
        int len = std::atoi(len_s);
        body.resize(len);
        std::cin.read(&body[0], len);
    }

    std::map<std::string,std::string> data;
    std::string format;
    std::string ctype(content_type);

    if (ctype.find("application/x-www-form-urlencoded") != std::string::npos) {
        data = parse_form(body);
        format = "www-form";
    }
    else if (ctype.find("application/json") != std::string::npos) {
        data["__json"] = body;
        format = "json";
    } else {
        format = "other";
    }

    // Timestamp
    char timebuf[64];
    std::strftime(timebuf, sizeof(timebuf), "%Y-%m-%dT%H:%M:%SZ", std::gmtime(std::time(nullptr)));

    // Output JSON
    std::cout << "Content-Type: application/json; charset=utf-8\r\n\r\n";
    std::cout << "{\n"
              << "  \"method\": \"" << method << "\",\n"
              << "  \"host\": \"" << host << "\",\n"
              << "  \"time\": \"" << timebuf << "\",\n"
              << "  \"ip\": \"" << ip << "\",\n"
              << "  \"user_agent\": \"" << json_escape(ua) << "\",\n"
              << "  \"content_type\": \"" << content_type << "\",\n"
              << "  \"format\": \"" << format << "\",\n"
              << "  \"raw_body\": \"" << json_escape(body) << "\",\n"
              << "  \"data\": {\n";

    bool first = true;
    for (auto& [k,v] : data) {
        if (!first) std::cout << ",\n";
        first = false;
        std::cout << "    \"" << json_escape(k) << "\": \"" << json_escape(v) << "\"";
    }
    std::cout << "\n  }\n}\n";

    return 0;
}

