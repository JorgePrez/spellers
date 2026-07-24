# Arquitectura EC2 y LibreOffice

## Servicios systemd

| Servicio | Rol |
|----------|-----|
| `libreoffice-uno.service` | LibreOffice headless, UNO puerto **2002** |
| `spellcheck-flask.service` | Flask `app.py` puerto **5000** |

### spellcheck-flask.service

```
User=ec2-user
WorkingDirectory=/home/ec2-user/libreoffice_spellcheck
ExecStart=/opt/libreoffice25.8/program/python /home/ec2-user/libreoffice_spellcheck/app.py
Environment=SPELLCHECK_BEARER_TOKEN=...
Requires=libreoffice-uno.service
```

### libreoffice-uno.service

```
User=ec2-user
ExecStart=/usr/local/bin/start-libreoffice-uno.sh
```

### start-libreoffice-uno.sh

```bash
exec /opt/libreoffice25.8/program/soffice \
    "-env:UserInstallation=file:///home/ec2-user/.libreoffice-uno" \
    --headless --nologo --nodefault --norestore \
    "--accept=socket,host=127.0.0.1,port=2002;urp;"
```

**Perfil LO del servicio:** `/home/ec2-user/.libreoffice-uno` (no `~/.config/libreoffice`).

## Reinicio tras cambios

```bash
sudo systemctl restart libreoffice-uno.service   # obligatorio si cambia diccionario
sleep 3
sudo systemctl restart spellcheck-flask.service
```

## Diccionario base (oficial)

- Ruta: `/opt/libreoffice25.8/share/extensions/dict-es/`
- Guatemala: `es_GT.dic` + `es_GT.aff` (~56.666 palabras)
- Locale: `es-GT`

## Comandos utiles

```bash
sudo systemctl status libreoffice-uno.service spellcheck-flask.service
curl http://localhost:5000/health
sudo journalctl -u libreoffice-uno.service -n 50 --no-pager
```
