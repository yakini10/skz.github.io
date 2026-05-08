// Désactive les avertissements concernant les fonctions obsolètes (inet_addr, etc.)
#define _WINSOCK_DEPRECATED_NO_WARNINGS

// Inclut les fichiers d'en-tête pour le travail réseau (sockets sous Windows)
#include <winsock2.h>
#include <ws2tcpip.h>

// Pour le travail avec les threads (CreateThread) et les sections critiques
#include <windows.h>

// Pour les entrées/sorties écran (cout, cin)
#include <iostream>

// Pour le type string
#include <string>

// Pour stocker la paire "pseudo" → "socket" (table des clients)
#include <map>

// Pour éviter d'écrire std::cout, std::string à chaque fois
using namespace std;

// Lie la bibliothèque de sockets (Ws2_32.lib)
#pragma comment(lib, "Ws2_32.lib")

// ==================== CONSTANTES ====================

// Port sur lequel le serveur écoutera les connexions
#define PORT 666

// Adresse IP du serveur (127.0.0.1 - localhost, pour les tests sur un seul ordinateur)
// Pour un fonctionnement en réseau, remplacer par votre vraie IP
#define SERVERADDR "127.0.0.1"

// ==================== VARIABLES GLOBALES ====================

// Compteur de clients connectés (nécessaire pour la macro PRINTNUSERS)
int nclients = 0;

// Table (dictionnaire) : pseudo du client → son socket (canal de communication)
// map est comme une liste qui permet de trouver rapidement une valeur par sa clé
map<string, SOCKET> clients;

// Section critique - protège les données partagées contre les modifications simultanées
// Plusieurs threads ne peuvent pas y entrer en même temps
CRITICAL_SECTION cs;

// Macro pour afficher le nombre d'utilisateurs
// \ permet d'écrire la macro sur plusieurs lignes
#define PRINTNUSERS \
if (nclients) \
    cout << "Utilisateurs en ligne : " << nclients << endl; \
else \
    cout << "Aucun utilisateur en ligne\n";

// ==================== PROTOTYPE DE FONCTION ====================

// Fonction qui sera exécutée dans un thread séparé pour chaque client
// DWORD WINAPI - type standard pour une fonction de thread sous Windows
// LPVOID - pointeur vers n'importe quelles données (ici le socket du client)
DWORD WINAPI ConToClient(LPVOID client_socket);

// ==================== FONCTION PRINCIPALE (POINT D'ENTRÉE) ====================

int main() {
    // WSADATA - structure contenant les infos de version de WinSock
    WSADATA wsaData;
    
    // mysocket - socket du serveur (permet d'accepter les connexions)
    SOCKET mysocket;

    // Affiche l'en-tête et l'IP du serveur dans la console
    cout << "SERVEUR DE CHAT\n";
    cout << "IP du serveur : " << SERVERADDR << endl;

    // ===== ÉTAPE 1 : INITIALISATION DE WIN SOCK =====
    // WSAStartup - prépare la bibliothèque pour le travail réseau
    // MAKEWORD(2,2) - demande la version 2.2
    // Si retourne autre chose que 0 -> erreur
    if (WSAStartup(MAKEWORD(2, 2), &wsaData)) {
        cout << "Erreur WSAStartup\n";
        return -1;  // Sortie avec erreur
    }

    // Crée la section critique (la prépare à l'utilisation)
    InitializeCriticalSection(&cs);

    // ===== ÉTAPE 2 : CRÉATION DU SOCKET =====
    // socket() - crée un socket
    // AF_INET - utilise IPv4
    // SOCK_STREAM - utilise TCP (transfert fiable avec établissement de connexion)
    // 0 - sélection automatique du protocole (TCP)
    mysocket = socket(AF_INET, SOCK_STREAM, 0);
    
    // Si le socket n'est pas créé (INVALID_SOCKET) -> erreur
    if (mysocket == INVALID_SOCKET) {
        cout << "Erreur socket\n";
        WSACleanup();             // ferme WinSock
        DeleteCriticalSection(&cs); // supprime la section critique
        return -1;
    }

    // ===== ÉTAPE 3 : CONFIGURATION DE L'ADRESSE SERVEUR =====
    // Structure pour stocker l'adresse du serveur (IPv4)
    sockaddr_in local_addr{};
    
    local_addr.sin_family = AF_INET;           // IPv4
    local_addr.sin_port = htons(PORT);         // port (htons convertit dans le bon format)
    local_addr.sin_addr.s_addr = inet_addr(SERVERADDR); // Adresse IP

    // ===== ÉTAPE 4 : LIAISON DU SOCKET À L'ADRESSE =====
    // bind() - lie le socket à une IP et un port spécifiques
    if (bind(mysocket, (sockaddr*)&local_addr, sizeof(local_addr))) {
        cout << "Erreur bind sur l'IP " << SERVERADDR << endl;
        closesocket(mysocket);    // ferme le socket
        WSACleanup();             // ferme WinSock
        DeleteCriticalSection(&cs);
        return -1;
    }

    // ===== ÉTAPE 5 : DÉBUT DE L'ÉCOUTE =====
    // listen() - commence à écouter les connexions entrantes
    // SOMAXCONN - taille maximale de la file d'attente (valeur max possible)
    if (listen(mysocket, SOMAXCONN)) {
        cout << "Erreur listen\n";
        closesocket(mysocket);
        WSACleanup();
        DeleteCriticalSection(&cs);
        return -1;
    }

    // Informe que le serveur est démarré et attend les clients
    cout << "Serveur démarré sur " << SERVERADDR << ":" << PORT << "\n";
    cout << "En attente de connexions...\n";

    // ===== ÉTAPE 6 : BOUCLE PRINCIPALE - ACCEPTATION DES CLIENTS =====
    // Variables pour stocker les infos du client qui se connecte
    sockaddr_in client_addr{};      // adresse du client
    int client_addr_size = sizeof(client_addr); // taille de la structure
    SOCKET client_socket;            // socket pour communiquer avec le client

    // accept() - attend une connexion. Quand un client se connecte, retourne son socket
    // La boucle tourne indéfiniment (jusqu'à fermeture du programme)
    while ((client_socket = accept(mysocket, (sockaddr*)&client_addr, &client_addr_size)) != INVALID_SOCKET) {
        
        // Alloue la mémoire pour une copie du socket client
        // Nécessaire car la variable client_socket peut changer à l'itération suivante
        SOCKET* pclient = new SOCKET;
        *pclient = client_socket;

        // Crée un nouveau thread pour servir le client qui vient de se connecter
        // Le thread exécutera la fonction ConToClient et recevra le socket en paramètre
        DWORD thID;
        CreateThread(NULL, 0, ConToClient, pclient, 0, &thID);
    }

    // ===== FIN (normalement jamais atteint en fonctionnement normal) =====
    closesocket(mysocket);
    WSACleanup();
    DeleteCriticalSection(&cs);
    return 0;
}

// ==================== FONCTION THREAD POUR UN CLIENT ====================
// Cette fonction s'exécute dans un thread séparé pour CHAQUE client connecté

DWORD WINAPI ConToClient(LPVOID client_socket) {
    // Extrait le socket du client depuis le paramètre
    SOCKET my_sock = *(SOCKET*)client_socket;
    
    // Libère la mémoire temporaire allouée dans main
    delete (SOCKET*)client_socket;

    // Tampon pour la réception des données (1024 octets)
    char buff[1024];
    // Longueur du message reçu
    int len;

    // ===== 1. REÇOIT LE PSEUDO DU CLIENT =====
    // recv() - reçoit les données du socket
    len = recv(my_sock, buff, 1024, 0);
    
    // Si rien reçu ou erreur -> déconnecte le client
    if (len <= 0) {
        closesocket(my_sock);
        return 0;
    }
    
    // Ajoute un zéro terminal à la fin de la chaîne (pour travailler comme une string)
    buff[len] = '\0';
    // Sauvegarde le pseudo dans une variable de type string
    string nick(buff);

    // ===== 2. AJOUTE LE CLIENT À LA LISTE COMMUNE =====
    // Entre dans la section critique - interdit aux autres threads de modifier les données partagées
    EnterCriticalSection(&cs);
    
    // Ajoute la paire "pseudo → socket" dans la table
    clients[nick] = my_sock;
    // Incrémente le compteur de clients
    nclients++;
    
    // Affiche dans la console du serveur l'information de connexion
    cout << "+ " << nick << " connecté\n";
    // Affiche le nombre d'utilisateurs en ligne (macro)
    PRINTNUSERS
    
    // Sort de la section critique - autorise les autres threads à travailler
    LeaveCriticalSection(&cs);

    // ===== 3. INFORME TOUS LES AUTRES CLIENTS DU NOUVEL UTILISATEUR =====
    string welcomeMsg = "=== " + nick + " a rejoint le chat ===\n";
    
    EnterCriticalSection(&cs);
    
    // Parcourt tous les clients dans la table
    for (auto& p : clients) {
        // Si ce n'est PAS le nouveau client lui-même (envoie à tous sauf lui)
        if (p.second != my_sock) {
            // send() - envoie le message au client
            send(p.second, welcomeMsg.c_str(), welcomeMsg.size(), 0);
        }
    }
    
    LeaveCriticalSection(&cs);

    // ===== 4. BOUCLE PRINCIPALE DE RÉCEPTION DES MESSAGES DE CE CLIENT =====
    // Tant que le client envoie des données (len > 0), les traite
    while ((len = recv(my_sock, buff, 1024, 0)) > 0) {
        // Ajoute le zéro terminal
        buff[len] = '\0';
        // Convertit en string pour faciliter le travail
        string msg(buff);

        // ===== DÉTERMINE LE TYPE DE MESSAGE =====
        // Si le premier caractère du message est @, c'est un message PRIVÉ
        if (msg[0] == '@') {
            
            // Cherche l'espace (séparateur entre le pseudo destinataire et le texte)
            // Exemple : "@Marie Salut" → espace après "Marie"
            size_t spacePos = msg.find(' ');
            
            // Si l'espace est trouvé (pas égal à npos - position inexistante)
            if (spacePos != string::npos) {
                // Extrait le pseudo du destinataire (du 1er caractère à l'espace, sans @)
                // msg.substr(début, longueur)
                string targetNick = msg.substr(1, spacePos - 1);
                
                // Extrait le texte du message (de l'espace+1 jusqu'à la fin)
                string privateMsg = "[MP de " + nick + "]: " + msg.substr(spacePos + 1) + "\n";
                
                // Cherche le destinataire dans la table
                EnterCriticalSection(&cs);
                
                // auto it = clients.find(targetNick) - cherche par clé
                // it != clients.end() - si trouvé (pas la fin de la table)
                auto it = clients.find(targetNick);
                if (it != clients.end()) {
                    // Envoie le message au destinataire
                    send(it->second, privateMsg.c_str(), privateMsg.size(), 0);
                    
                    // Envoie une confirmation à l'expéditeur (pour qu'il sache que c'est parti)
                    string confirm = "[MP à " + targetNick + "]: " + msg.substr(spacePos + 1) + "\n";
                    send(my_sock, confirm.c_str(), confirm.size(), 0);
                } else {
                    // Si l'utilisateur avec ce pseudo n'est pas trouvé - envoie une erreur
                    string error = "Utilisateur " + targetNick + " non trouvé\n";
                    send(my_sock, error.c_str(), error.size(), 0);
                }
                
                LeaveCriticalSection(&cs);
            }
        } 
        // Sinon (le premier caractère n'est pas @) - c'est un message PUBLIC
        else {
            // Formate le message avec le pseudo de l'expéditeur
            // Exemple : "[Diana]: Bonjour tout le monde !"
            string publicMsg = "[" + nick + "]: " + msg + "\n";
            
            // Affiche ce message dans la console du serveur (pour le journal)
            cout << publicMsg;
            
            // Envoie le message À TOUS les clients
            EnterCriticalSection(&cs);
            
            // Parcourt toute la table des clients
            for (auto& p : clients) {
                // envoie à chacun
                send(p.second, publicMsg.c_str(), publicMsg.size(), 0);
            }
            
            LeaveCriticalSection(&cs);
        }
    }

    // ===== 5. LE CLIENT S'EST DÉCONNECTÉ (sorti de la boucle) =====
    
    // Le supprime de la table
    EnterCriticalSection(&cs);
    
    // erase() - supprime un élément de la map par sa clé (pseudo)
    clients.erase(nick);
    // Diminue le compteur
    nclients--;
    
    cout << "- " << nick << " déconnecté\n";
    PRINTNUSERS
    
    // Informe tous les autres que ce client a quitté le chat
    string leaveMsg = "=== " + nick + " a quitté le chat ===\n";
    for (auto& p : clients) {
        send(p.second, leaveMsg.c_str(), leaveMsg.size(), 0);
    }
    
    LeaveCriticalSection(&cs);
    
    // Ferme le socket du client
    closesocket(my_sock);
    
    // Termine le thread (retourne 0 : succès)
    return 0;
}
