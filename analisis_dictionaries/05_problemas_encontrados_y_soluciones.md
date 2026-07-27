# Problemas encontrados y soluciones

## 1. Copiar carpeta a share/extensions no carga el diccionario

**Sintoma:** `abdominoplastia` → `isValid=False`; solo `hola` → True.

**Causa:** LibreOffice no ejecuta `dictionaries.xcu` de carpetas sueltas sin registro.

**Solucion:** `unopkg add --shared dict-ua-med.oxt`

---

## 2. CRLF (Windows) en .sh y .dic

**Sintoma:** `set: pipefail: invalid option name` en bash.

**Sintoma:** Palabras al final del .dic ignoradas; `sed '1s/^1000$/1002/'` no hace match (`1000\r`).

**Solucion:**

```bash
sed -i 's/\r$//' archivo
```

En PC: VS Code → UTF-8 + LF. `.gitattributes` en `dict-ua-med/`.

---

## 3. Contador incorrecto en linea 1 del .dic

**Sintoma:** Palabras anadidas al final (ej. Cardiología) no pasan.

**Causa:** Hunspell lee solo N palabras segun linea 1.

**Solucion:**

```bash
WORDS=$(tail -n +2 ua_med_GT.dic | sed '/^\s*$/d' | wc -l)
sed -i "1s/.*/$WORDS/" ua_med_GT.dic
```

---

## 4. Encoding Windows-1252 vs UTF-8 en XML

**Sintoma unopkg:**

```
Input is not proper UTF-8
Bytes: 0xE9 0x64 0x69 0x63
Line: 6  (description.xml, "Médico")
```

**Causa:** Archivo guardado en cp1252 con `encoding="UTF-8"` en XML.

**Solucion:**

- Guardar `description.xml` en UTF-8 o solo ASCII (`Medico` sin tilde en titulo).
- `install_dict_ua_med.sh` convierte XML y .dic a UTF-8 con Python antes de empaquetar.

---

## 5. Script diagnose con SyntaxError en Python

**Sintoma:** `SyntaxError: 'utf-8' codec can't decode byte 0xed` en `Cardiología` dentro del .sh.

**Causa:** El heredoc Python en el .sh tenia tildes en cp1252.

**Solucion:** Usar escapes Unicode en Python: `"Cardiolog\u00eda"`. Script `diagnose_dict_ec2.sh` actualizado en repo.

---

## 6. Confusion: palabras que parecen del diccionario UA pero no

Ejemplo: `polineuropatía` pasaba antes de instalar UA → puede estar en `es_GT.dic` oficial.

Para probar el UA usar palabras **solo** en `ua_med_GT.dic`, ej. `abdominoplastia`.

---

## 7. Extension vieja "UA Diccionario Medicina (prueba)"

Aparecia en log de `unopkg` junto con la nueva. Revisar:

```bash
/opt/libreoffice25.8/program/unopkg list --shared
/opt/libreoffice25.8/program/unopkg remove --shared <id-viejo>
```

Identificador actual: `org.ua.dictionaries.med-gt`.
