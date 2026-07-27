# Checklist: rehacer en carpeta con VS Code UTF-8

Usar este checklist en el **nuevo chat / nuevo workspace**.

## A. Configuracion del editor

- [ ] VS Code / Cursor: encoding **UTF-8** (no Windows 1252)
- [ ] Fin de linea: **LF**
- [ ] Opcional en `.vscode/settings.json`:

```json
{
  "files.encoding": "utf8",
  "files.eol": "\n"
}
```

- [ ] Confirmar `.gitattributes` en `dict-ua-med/`:

```
dictionaries/dict-ua-med/* text eol=lf working-tree-encoding=UTF-8
```

## B. Archivos a recrear o copiar limpios

- [ ] `ua_med_GT.dic` - UTF-8, linea 1 = conteo correcto
- [ ] `ua_med_GT.aff` - `SET UTF-8` o copiar `es_GT.aff` en install
- [ ] `dictionaries.xcu` - UTF-8
- [ ] `description.xml` - UTF-8 (o ASCII sin tildes en XML)
- [ ] `META-INF/manifest.xml`
- [ ] `install_dict_ua_med.sh` - LF, con bloque Python UTF-8
- [ ] `diagnose_dict_ec2.sh` - Python con `\u00ed` etc., no tildes literales en .sh

## C. Validar localmente antes de subir

```bash
python verify_dic.py
python -c "open('description.xml',encoding='utf-8').read(); print('XML UTF-8 OK')"
file ua_med_GT.dic   # en Linux/WSL
```

## D. Desplegar en EC2

```bash
cd .../dictionaries/dict-ua-med
sed -i 's/\r$//' install_dict_ua_med.sh ua_med_GT.dic description.xml
sudo bash install_dict_ua_med.sh
```

## E. Confirmar en EC2

- [ ] `unopkg list --shared` muestra `org.ua.dictionaries.med-gt`
- [ ] Prueba Python: `abdominoplastia` → True
- [ ] Prueba Python: `Cardiología` → True (con `\u00eda` en script)
- [ ] `Introduccion` → False
- [ ] Subir cronograma de prueba en syllabus

## F. Limpieza

- [ ] Eliminar `/opt/libreoffice25.8/share/extensions/dict-ua-med/` si existe (copia manual vieja)
- [ ] Quitar extensiones UA duplicadas con `unopkg remove --shared` si hay ids viejos

## G. Mensaje sugerido para el nuevo chat

> Continuamos diccionario medico UA para spellcheck EC2. Leer `analisis/diccionario_medico_ua_spellcheck/`. Locale es-GT, instalacion con `unopkg add --shared`, sin listas Python. Repo en UTF-8. Ultimo estado: unopkg OK, falta confirmar isValid tras prueba Python.
