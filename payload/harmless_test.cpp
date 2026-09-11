/*
 * harmless_test.cpp
 * -------------------
 * Simulates a malicious attachment being executed by a target.
 * It does NOTHING harmful -- it just prints a message and pings
 * a local "listener" URL (payload_listener.php, once built) so
 * simulation_logs.is_attachment_download can be marked = 1.
 *
 * Usage:
 *   ./harmless_test <tracking_token>
 *
 * Compile:
 *   g++ harmless_test.cpp -o harmless_test
 */

#include <iostream>
#include <string>
#include <cstdlib>

int main(int argc, char* argv[]) {
    std::string token = "unknown-token";

    if (argc > 1) {
        token = argv[1];
    }

    // 1. Harmless simulated "payload" behaviour -- just prints a message.
    std::cout << "[SIMULATED PAYLOAD] Attachment executed successfully.\n";
    std::cout << "[SIMULATED PAYLOAD] Tracking token: " << token << "\n";
    std::cout << "[SIMULATED PAYLOAD] No real action taken -- this is a training exercise.\n";

    // 2. Ping the (future) PHP listener to log that execution happened.
    //    Uses curl via system() call for simplicity.
    std::string url = "http://localhost/cybershield/payload_listener.php?uid=" + token;
    std::string command = "curl -s \"" + url + "\" > /dev/null 2>&1";

    std::cout << "[SIMULATED PAYLOAD] Reporting execution to: " << url << "\n";
    int result = std::system(command.c_str());

    if (result == 0) {
        std::cout << "[SIMULATED PAYLOAD] Ping sent (listener may not exist yet -- that's expected for now).\n";
    }

    return 0;
}
