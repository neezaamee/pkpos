#!/usr/bin/env python3
"""
PK-POS FTPS Deployment Payload Uploader
Uploads deploy_payload.zip, .deploy_token, and deploy_extract.php to Hostinger via FTPS.
"""
import os
import sys
import ftplib
import ssl

def main():
    server = os.environ.get('FTP_CLEAN_SERVER') or os.environ.get('FTP_SERVER', '').strip()
    target_dir = os.environ.get('FTP_TARGET_DIR', 'domains/01pos.01output.com/public_html/').strip()
    username = os.environ.get('FTP_USERNAME', '').strip()
    password = os.environ.get('FTP_PASSWORD', '').strip()

    if not server or not username or not password:
        print("::error::Missing FTP credentials. Check secrets.")
        sys.exit(1)

    print(f"Connecting to {server}:21 via FTPS...")

    class HostingerFTPS(ftplib.FTP_TLS):
        def ssl_wrap(self, sock):
            ctx = ssl.create_default_context()
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
            return ctx.wrap_socket(sock, server_hostname=server)

    try:
        ftp = HostingerFTPS()
        ftp.connect(server, 21, timeout=60)
        ftp.auth()
        ftp.login(username, password)
        ftp.prot_p()

        # Navigate / create target directories
        for part in target_dir.strip('/').split('/'):
            if part:
                try:
                    ftp.cwd(part)
                except Exception:
                    ftp.mkd(part)
                    ftp.cwd(part)

        print(f"Current remote directory: {ftp.pwd()}")

        upload_files = ["deploy_extract.php", ".deploy_token", "deploy_payload.zip"]
        for fname in upload_files:
            if not os.path.exists(fname):
                print(f"::error::File {fname} does not exist locally.")
                sys.exit(1)
            size_mb = os.path.getsize(fname) / (1024 * 1024)
            print(f"Uploading {fname} ({size_mb:.2f} MB)...")
            with open(fname, "rb") as f:
                ftp.storbinary(f"STOR {fname}", f, blocksize=65536)
            print(f"✅ {fname} uploaded.")

        ftp.quit()
        print("FTPS upload completed successfully.")
    except Exception as err:
        print(f"::error::FTPS upload error: {err}")
        sys.exit(1)

if __name__ == "__main__":
    main()
