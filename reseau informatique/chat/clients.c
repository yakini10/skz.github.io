// Désactive les avertissements concernant les fonctions obsolètes
#define _WINSOCK_DEPRECATED_NO_WARNINGS

// Inclut les en-têtes pour les sockets, les threads, les entrées/sorties, les chaînes
#include <winsock2.h>
#include <ws2tcpip.h>
#include <windows.h>
#include <iostream>
#include <string>
using namespace std;

// Lie la bibliothèque de sockets
#pragma comment(lib, "Ws2_32.lib")

// ==================== CONSTANTES ====================

// Port du serveur (doit correspondre à celui du serveur)
#define PORT 666

// Adresse IP du serveur (127.0.0.1 - localhost, pour les tests)
// Pour un fonctionnement en réseau, remplacer par la vraie IP du serveur
#define SERVERADDR "127.0.0.1"

// ==================== FONCTION THREAD POUR LA RÉCEPTION DES MESSAGES ====================
// Cette fonction s'exécute dans un thread séparé et écoute constamment le serveur

DWORD WINAPI ReceiveMessages(LPVOID sock) {
    // Récupère le socket depuis le paramètre
    SOCKET s = *(SOCKET*)sock;
    
    // Tampon pour la réception
    char buff[1024];
    int len;
    
    // Boucle infinie de réception des messages
    while ((len = recv(s, buff, 1024, 0)) > 0) {
        // Ajoute le zéro terminal
        buff[len] = '\0';
        // Affiche le message reçu à l'écran (directement dans le chat)
        cout << buff;
    }
    
    return 0;
}

// ==================== FONCTION PRINCIPALE ====================

int main() {
    WSADATA wsaData;
    SOCKET my_sock;
    
    // Message d'accueil
    cout << "CLIENT DE CHAT\n";
    cout << "Connexion au serveur : " << SERVERADDR << ":" << PORT << endl;
    
    // 1. Initialise WinSock
    if (WSAStartup(MAKEWORD(2, 2), &wsaData)) {
        cout << "Erreur WSAStartup\n";
        return -1;
    }
    
    // 2. Crée le socket
    my_sock = socket(AF_INET, SOCK_STREAM, 0);
    if (my_sock == INVALID_SOCKET) {
        cout << "Erreur socket\n";
        WSACleanup();
        return -1;
    }
    
    // 3. Configure l'adresse du serveur
    sockaddr_in dest_addr{};
    dest_addr.sin_family = AF_INET;      // IPv4
    dest_addr.sin_port = htons(PORT);    // port 666
    dest_addr.sin_addr.s_addr = inet_addr(SERVERADDR); // IP du serveur
    
    // 4. Se connecte au serveur
    // connect() - établit la connexion avec le serveur
    if (connect(my_sock, (sockaddr*)&dest_addr, sizeof(dest_addr))) {
        cout << "Erreur de connexion à " << SERVERADDR << endl;
        system("pause");  // Attend une touche pour que l'utilisateur voie l'erreur
        return -1;
    }
    
    // Connexion réussie
    cout << "Connecté au serveur !\n";
    
    // 5. Demande le pseudo de l'utilisateur
    cout << "Entrez votre pseudo : ";
    string nick;
    getline(cin, nick);  // lit une ligne depuis le clavier
    
    // 6. Envoie le pseudo au serveur (le serveur nous ajoutera à sa table)
    send(my_sock, nick.c_str(), nick.size(), 0);
    
    // 7. Lance un thread séparé pour recevoir les messages du serveur
    // Pendant que nous tapons quelque chose, ce thread recevra et affichera les messages en arrière-plan
    DWORD thID;
    CreateThread(NULL, 0, ReceiveMessages, &my_sock, 0, &thID);
    
    // 8. Affiche les aides sur les commandes du chat
    cout << "\n===== CHAT =====" << endl;
    cout << "  Message public : tapez simplement le texte" << endl;
    cout << "  Message privé : @pseudo message" << endl;
    cout << "  Quitter : quit\n" << endl;
    
    // 9. Boucle principale - lit la saisie de l'utilisateur et l'envoie au serveur
    string msg;
    while (true) {
        // Lit une ligne depuis la console (ce que l'utilisateur tape)
        getline(cin, msg);
        
        // Si l'utilisateur a tapé "quit" - quitte le chat
        if (msg == "quit") break;
        
        // Envoie le message au serveur
        // Le serveur déterminera lui-même s'il est public ou privé (selon la présence de @)
        send(my_sock, msg.c_str(), msg.size(), 0);
    }
    
    // 10. Fin du travail
    closesocket(my_sock);   // ferme le socket
    WSACleanup();           // nettoie WinSock
    return 0;
}
