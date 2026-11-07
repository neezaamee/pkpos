#!/usr/bin/env python3
"""
PK-POS FTP Target Path Detector
Resolves FTP hostname, connects via FTPS, and selects the correct document root.
"""
import os
import sys
import re
import socket
import ftplib
import ssl

def main():
    raw_server = os.environ.get('FTP_SERVER', '').strip()
    raw_username = os.environ.get('FTP_USERNAME', '').strip()
    raw_password = os.environ.get('FTP_PASSWORD', '').strip()
    custom_dir = os.environ.get('FTP_REMOTE_DIR', '').strip()

    if not raw_server or not raw_username or not raw_password:
        print("::error::Missing FTP credentials! Ensure FTP_SERVER, FTP_USERNAME, and FTP_PASSWORD secrets are set.")
        sys.exit(1)

    # Sanitize server hostname
    clean_server = re.sub(r'^[a-zA-Z]+://', '', raw_server)
    clean_server = clean_server.split('/')[0].split(':')[0].strip().strip('"\'')
    if not clean_server:
        clean_server = "46.202.138.81"

    try:
        ip = socket.gethostbyname(clean_server)
        print(f"✅ Host {clean_server} resolved to {ip}")
    except Exception as dns_err:
        print(f"⚠️ DNS lookup failed for '{clean_server}': {dns_err}. Falling back to 46.202.138.81")
        clean_server = "46.202.138.81"

    print(f"Connecting to {clean_server}:21 via FTPS...")

    class HostingerFTPS(ftplib.FTP_TLS):
        def ssl_wrap(self, sock):
            ctx = ssl.create_default_context()
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
            return ctx.wrap_socket(sock, server_hostname=clean_server)

    target_dir = "./"
    try:
        ftp = HostingerFTPS()
        ftp.connect(clean_server, 21, timeout=20)
        ftp.auth()
        ftp.login(raw_username, raw_password)
        ftp.prot_p()
        lines = []
        ftp.retrlines('LIST', lines.append)
        entries = [line.split()[-1] for line in lines if line.strip()]
        print("Remote root entries:", entries)

        if custom_dir:
            target_dir = custom_dir
        elif "domains" in entries:
            target_dir = "domains/01pos.01output.com/public_html/"
        elif "public_html" in entries:
            target_dir = "public_html/"
        else:
            target_dir = "./"

        if not target_dir.endswith('/'):
            target_dir += '/'

        print(f"✅ Selected target directory: {target_dir}")
        ftp.quit()
    except Exception as e:
        print(f"⚠️ FTP probe notice: {e}. Falling back to default directory.")
        target_dir = custom_dir if custom_dir else "domains/01pos.01output.com/public_html/"
        if not target_dir.endswith('/'):
            target_dir += '/'

    github_env = os.environ.get('GITHUB_ENV')
    if github_env:
        with open(github_env, 'a') as f:
            f.write(f"FTP_CLEAN_SERVER={clean_server}\n")
            f.write(f"FTP_TARGET_DIR={target_dir}\n")

if __name__ == "__main__":
    main()
