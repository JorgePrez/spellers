<?php
/**
 * Bitacoras syllabus catedratico (OCI).
 * Patron basado en adm_academico_detalle_curso2.php
 */

function uac_limpiarHTMLVacio($html) {
    if (empty($html)) {
        return null;
    }
    $texto = strip_tags($html);
    $texto = str_replace(['&nbsp;', '&amp;nbsp;'], '', $texto);
    $texto = trim($texto);
    return ($texto === '') ? null : $html;
}

/*function uac_sanitizarHtmlBiblio($html) {
    if ($html === null || $html === '') {
        return '';
    }
    return strip_tags($html, '<b><i><u><strong><em><br><p><a>');
}*/

function uac_sanitizarHtmlBiblio($html) {
    if ($html === null || $html === '') {
        return '';
    }

    $html = trim((string) $html);
    $html = preg_replace('/<!--\[if[^\]]*\]>.*?<!\[endif\]-->/is', '', $html);
    $html = preg_replace('/<\?xml[^>]*\?>/i', '', $html);
    $html = preg_replace('/<o:[^>]*>.*?<\/o:[^>]*>/is', '', $html);
    $html = preg_replace('/<w:[^>]*>.*?<\/w:[^>]*>/is', '', $html);
    $html = preg_replace('/<m:[^>]*>.*?<\/m:[^>]*>/is', '', $html);
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    //quitar p de aqui: 

    return strip_tags($html, '<b><i><u><strong><em><br><a>');
}

function uac_normalizarReferenciaBiblioPost($html) {
    $html = uac_sanitizarHtmlBiblio(trim((string) $html));
    return uac_limpiarHTMLVacio($html);
}

function uac_renderReferenciaBiblioVista($html) {
    $html = trim((string) $html);
    if ($html === '' || uac_limpiarHTMLVacio($html) === null) {
        return '<em>Sin informaci&oacute;n</em>';
    }
    return $html;
}

function uac_getReferenciaBiblioEv($conn, $intBiblioId) {
    $intBiblioId = intval($intBiblioId);
    if ($intBiblioId <= 0) {
        return '';
    }

    $sql = "SELECT REFERENCIA_COMPLETA
            FROM SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA
            WHERE SYLLABUS_UAC_BIBLIOGRAFIA = :bid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':bid', $intBiblioId, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
    oci_free_statement($stid);

    return ($row && isset($row['REFERENCIA_COMPLETA'])) ? (string) $row['REFERENCIA_COMPLETA'] : '';
}

function uac_getReferenciaBiblioEvLog($conn, $intLogId) {
    $intLogId = intval($intLogId);
    if ($intLogId <= 0) {
        return '';
    }

    $sql = "SELECT REFERENCIA_COMPLETA
            FROM SYLLABUS_UAC_BIBLIO_LOG
            WHERE LOG_ID = :lid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':lid', $intLogId, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
    oci_free_statement($stid);

    return ($row && isset($row['REFERENCIA_COMPLETA'])) ? (string) $row['REFERENCIA_COMPLETA'] : '';
}

function uac_guardarReferenciaBiblioEv($conn, $intBiblioId, $intSyllabusUAC, $html, $intUser) {
    $intBiblioId = intval($intBiblioId);
    $intSyllabusUAC = intval($intSyllabusUAC);
    $intUser = intval($intUser);
    if ($intBiblioId <= 0 || $intSyllabusUAC <= 0) {
        return false;
    }

    $html = uac_normalizarReferenciaBiblioPost($html);
    if ($html === null) {
        return false;
    }

    $html = utf8_decode($html);

    $sql = "UPDATE SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA
            SET REFERENCIA_COMPLETA = EMPTY_CLOB(),
                MOD_USER = :mod_user,
                MOD_FECHA = SYSDATE
            WHERE SYLLABUS_UAC_BIBLIOGRAFIA = :biblio_id
              AND SYLLABUS_UA_CATEDRATICO = :syl_uac
            RETURNING REFERENCIA_COMPLETA INTO :lob_referencia";

    $stid = oci_parse($conn, $sql);
    $lobRef = oci_new_descriptor($conn, OCI_D_LOB);
    oci_bind_by_name($stid, ':mod_user', $intUser, -1, SQLT_INT);
    oci_bind_by_name($stid, ':biblio_id', $intBiblioId, -1, SQLT_INT);
    oci_bind_by_name($stid, ':syl_uac', $intSyllabusUAC, -1, SQLT_INT);
    oci_bind_by_name($stid, ':lob_referencia', $lobRef, -1, OCI_B_CLOB);
    oci_execute($stid, OCI_NO_AUTO_COMMIT);
    $lobRef->write($html);
    $lobRef->free();
    oci_free_statement($stid);

    return true;
}

function uac_insertReferenciaBiblioEv($conn, $intSyllabusUAC, $html, $intUser, $bolContenidoDesdeBd = false) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $intUser = intval($intUser);
    if ($intSyllabusUAC <= 0) {
        return 0;
    }

    if ($bolContenidoDesdeBd) {
        $html = trim((string) $html);
        if ($html === '' || uac_limpiarHTMLVacio($html) === null) {
            return 0;
        }
    } else {
        $html = uac_normalizarReferenciaBiblioPost($html);
        if ($html === null) {
            return 0;
        }
        $html = utf8_decode($html);
    }

    $sql = "INSERT INTO SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA
                (SYLLABUS_UAC_BIBLIOGRAFIA, SYLLABUS_UA_CATEDRATICO,
                 REFERENCIA_COMPLETA, ADD_USER, ADD_FECHA)
            VALUES
                (SEQ_SYLLABUS_UAC_BIBLIO.NEXTVAL, :syl_uac,
                 EMPTY_CLOB(), :add_user, SYSDATE)
            RETURNING SYLLABUS_UAC_BIBLIOGRAFIA, REFERENCIA_COMPLETA
            INTO :new_id, :lob_referencia";

    $stid = oci_parse($conn, $sql);
    $lobRef = oci_new_descriptor($conn, OCI_D_LOB);
    $intNewId = 0;
    oci_bind_by_name($stid, ':syl_uac', $intSyllabusUAC, -1, SQLT_INT);
    oci_bind_by_name($stid, ':add_user', $intUser, -1, SQLT_INT);
    oci_bind_by_name($stid, ':new_id', $intNewId, -1, SQLT_INT);
    oci_bind_by_name($stid, ':lob_referencia', $lobRef, -1, OCI_B_CLOB);
    oci_execute($stid, OCI_NO_AUTO_COMMIT);
    $lobRef->write($html);
    $lobRef->free();
    oci_free_statement($stid);

    return intval($intNewId);
}

function uac_bitacora_camposValidos() {
    return [
        'NORMAS_REGLAS'       => 'Normas y reglas operativas del curso',
        'USO_IA'              => 'Uso de IA en el curso',
        'PENSAMIENTO_CRITICO' => 'Desarrollo del pensamiento critico',
    ];
}

function uac_bitacora_camposClob() {
    return ['NORMAS_REGLAS', 'USO_IA', 'PENSAMIENTO_CRITICO'];
}

function uac_bitacora_esCampoClob($strCampo) {
    return in_array($strCampo, uac_bitacora_camposClob(), true);
}

function uac_bitacora_nombreArchivoDesdePath($strPath) {
    $strPath = trim((string) $strPath);
    if ($strPath === '') {
        return '(sin archivo)';
    }
    $strNombre = basename(parse_url($strPath, PHP_URL_PATH));
    return ($strNombre !== '') ? $strNombre : $strPath;
}

function uac_bitacora_botonDescargaCronograma($strPath, $intLogId, $strNombreArchivo = '') {
    $strPath   = trim((string) $strPath);
    $intLogId  = intval($intLogId);
    $strNombre = ($strNombreArchivo !== '') ? trim($strNombreArchivo) : uac_bitacora_nombreArchivoDesdePath($strPath);

    if (($strPath === '' && $intLogId <= 0) || $strNombre === '' || $strNombre === '(sin archivo)') {
        return htmlspecialchars($strNombre !== '' ? $strNombre : '(sin archivo)', ENT_QUOTES, 'ISO-8859-1');
    }

    $strNombreEsc = htmlspecialchars($strNombre, ENT_QUOTES, 'ISO-8859-1');

    return '<button type="button" class="bitacora-btn-descarga" '
         . 'onclick="fntDescargarCronogramaBitacora(' . $intLogId . ')" '
         . 'title="Descargar">' . $strNombreEsc . '</button>';
}

function uac_bitacora_botonDescargaCronoId($strNombreArchivo, $intCronoId) {
    $intCronoId   = intval($intCronoId);
    $strNombre    = trim((string) $strNombreArchivo);
    if ($strNombre === '' || $intCronoId <= 0) {
        return htmlspecialchars($strNombre !== '' ? $strNombre : '(sin archivo)', ENT_QUOTES, 'ISO-8859-1');
    }
    $strNombreEsc = htmlspecialchars($strNombre, ENT_QUOTES, 'ISO-8859-1');
    return '<button type="button" class="bitacora-btn-descarga" '
         . 'onclick="fntDescargarCronoAdjunto(' . $intCronoId . ')" '
         . 'title="Descargar">' . $strNombreEsc . '</button>';
}

function uac_bitacora_fetchAll($stid) {
    $arr = [];
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS)) {
        $arr[] = $row;
    }
    return $arr;
}

function uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $intCimp = intval($intCimp);
    if ($intSyllabusUAC <= 0 || $intCimp <= 0) {
        return false;
    }
    $sql = "SELECT 1 AS OK
            FROM SYLLABUS_UA_CATEDRATICO
            WHERE SYLLABUS_UA_CATEDRATICO = :id
              AND CURSO_IMPARTIDO = :cimp
              AND FECHA_INICIO IS NULL
              AND FECHA_FIN IS NULL";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_bind_by_name($stid, ':cimp', $intCimp, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    return !empty($row);
}

function uac_bitacora_verificarEval($conn, $intEvalId, $intSyllabusUAC) {
    $intEvalId = intval($intEvalId);
    $intSyllabusUAC = intval($intSyllabusUAC);
    if ($intEvalId <= 0 || $intSyllabusUAC <= 0) {
        return false;
    }
    $sql = "SELECT 1 AS OK
            FROM SYLLABUS_UA_CATEDRATICO_EVALUACION
            WHERE SYLLABUS_UAC_EVALUACION = :eid
              AND SYLLABUS_UA_CATEDRATICO = :sid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':eid', $intEvalId, -1, SQLT_INT);
    oci_bind_by_name($stid, ':sid', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    return !empty($row);
}

function uac_bitacora_verificarBiblio($conn, $intBiblioId, $intSyllabusUAC) {
    $intBiblioId = intval($intBiblioId);
    $intSyllabusUAC = intval($intSyllabusUAC);
    if ($intBiblioId <= 0 || $intSyllabusUAC <= 0) {
        return false;
    }
    $sql = "SELECT 1 AS OK
            FROM SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA
            WHERE SYLLABUS_UAC_BIBLIOGRAFIA = :bid
              AND SYLLABUS_UA_CATEDRATICO = :sid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':bid', $intBiblioId, -1, SQLT_INT);
    oci_bind_by_name($stid, ':sid', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    return !empty($row);
}

function uac_bitacora_verificarExp($conn, $intExpId, $intSyllabusUAC) {
    $intExpId = intval($intExpId);
    $intSyllabusUAC = intval($intSyllabusUAC);
    if ($intExpId <= 0 || $intSyllabusUAC <= 0) {
        return false;
    }
    $sql = "SELECT 1 AS OK
            FROM SYLLABUS_UA_CATEDRATICO_EXPERIENCIA
            WHERE SYLLABUS_UAC_EXPERIENCIA = :eid
              AND SYLLABUS_UA_CATEDRATICO = :sid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':eid', $intExpId, -1, SQLT_INT);
    oci_bind_by_name($stid, ':sid', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    return !empty($row);
}

function uac_getClobLog($conn, $intLogId, $strCampo = 'NORMAS_REGLAS') {
    if (!uac_bitacora_esCampoClob($strCampo)) {
        return '';
    }
    $intLogId = intval($intLogId);
    $sql = "SELECT {$strCampo} AS VALOR_CLOB FROM SYLLABUS_UAC_LOG WHERE LOG_ID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intLogId, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
    oci_free_statement($stid);
    if (!$row) {
        return '';
    }
    $clob = $row['VALOR_CLOB'] ?? '';
    if (is_object($clob) && method_exists($clob, 'load')) {
        return $clob->load();
    }
    return (string) $clob;
}

function uac_getClobActualCampo($conn, $intSyllabusUAC, $strCampo = 'NORMAS_REGLAS') {
    if (!uac_bitacora_esCampoClob($strCampo)) {
        return '';
    }
    $intSyllabusUAC = intval($intSyllabusUAC);
    $sql = "SELECT {$strCampo} AS VALOR_CLOB FROM SYLLABUS_UA_CATEDRATICO WHERE SYLLABUS_UA_CATEDRATICO = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
    oci_free_statement($stid);
    if (!$row) {
        return '';
    }
    $clob = $row['VALOR_CLOB'] ?? '';
    if (is_object($clob) && method_exists($clob, 'load')) {
        return $clob->load();
    }
    return (string) $clob;
}

function uac_getClobActualNormas($conn, $intSyllabusUAC) {
    return uac_getClobActualCampo($conn, $intSyllabusUAC, 'NORMAS_REGLAS');
}

function uac_getLogCampoClob($conn, $intSyllabusUAC, $strCampo) {
    if (!uac_bitacora_esCampoClob($strCampo)) {
        return [];
    }
    $intSyllabusUAC = intval($intSyllabusUAC);

    $sql = "SELECT L.LOG_ID,
                   L.ADD_FECHA_LOG,
                   L.USUARIO
            FROM (
                SELECT L.LOG_ID,
                       L.ADD_FECHA_LOG,
                       p.USUARIO,
                       L.{$strCampo} AS VALOR_ANTERIOR,
                       L_ANT.{$strCampo} AS VALOR_PREVIO,
                       S.{$strCampo} AS VALOR_ACTUAL_TABLA
                FROM (
                    SELECT LOG_ID,
                           SYLLABUS_UA_CATEDRATICO,
                           {$strCampo},
                           ADD_FECHA_LOG,
                           ADD_USER_LOG,
                           ADD_USER,
                           LAG(LOG_ID) OVER (ORDER BY ADD_FECHA_LOG, LOG_ID) AS LOG_ID_ANTERIOR
                    FROM SYLLABUS_UAC_LOG
                    WHERE SYLLABUS_UA_CATEDRATICO = :id
                      AND TIPO_OPERACION = 'U'
                      AND {$strCampo} IS NOT NULL
                ) L
                LEFT JOIN SYLLABUS_UAC_LOG L_ANT
                    ON L.LOG_ID_ANTERIOR = L_ANT.LOG_ID
                LEFT JOIN PERSONA p
                    ON NVL(L.ADD_USER_LOG, L.ADD_USER) = p.PERSONA
                LEFT JOIN SYLLABUS_UA_CATEDRATICO S
                    ON L.SYLLABUS_UA_CATEDRATICO = S.SYLLABUS_UA_CATEDRATICO
                   AND S.FECHA_INICIO IS NULL
                   AND S.FECHA_FIN IS NULL
            ) L
            WHERE (
                L.VALOR_PREVIO IS NULL
                OR DBMS_LOB.COMPARE(L.VALOR_ANTERIOR, L.VALOR_PREVIO) != 0
            )
            AND (
                L.VALOR_ACTUAL_TABLA IS NULL
                OR DBMS_LOB.COMPARE(L.VALOR_ANTERIOR, L.VALOR_ACTUAL_TABLA) != 0
            )
            ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC";

    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

function uac_getLogCampoNormas($conn, $intSyllabusUAC) {
    return uac_getLogCampoClob($conn, $intSyllabusUAC, 'NORMAS_REGLAS');
}

function uac_getLogEval($conn, $intEvalId) {
    $intEvalId = intval($intEvalId);
    $sql = "SELECT L.LOG_ID,
                   L.RUBRO,
                   L.PORCENTAJE,
                   TO_CHAR(NVL(L.MOD_FECHA, L.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') AS FECHA_LOG,
                   NVL(p.USUARIO, TO_CHAR(L.ADD_USER_LOG)) AS USUARIO
            FROM SYLLABUS_UAC_EVAL_LOG L
            LEFT JOIN PERSONA p ON L.ADD_USER_LOG = p.PERSONA
            WHERE L.SYLLABUS_UAC_EVALUACION = :eid
              AND L.TIPO_OPERACION = 'U'
            ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':eid', $intEvalId, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

function uac_getEvalActivos($conn, $intSyllabusUAC) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $sql = "SELECT e.SYLLABUS_UAC_EVALUACION,
                   e.RUBRO,
                   e.PORCENTAJE,
                   TO_CHAR(e.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   NVL(p.USUARIO, TO_CHAR(e.ADD_USER)) AS USUARIO_CREACION
            FROM SYLLABUS_UA_CATEDRATICO_EVALUACION e
            LEFT JOIN PERSONA p ON e.ADD_USER = p.PERSONA
            WHERE e.SYLLABUS_UA_CATEDRATICO = :id
            ORDER BY e.ADD_FECHA, e.SYLLABUS_UAC_EVALUACION";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

function uac_getEvalEliminados($conn, $intSyllabusUAC) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $sql = "SELECT L.SYLLABUS_UAC_EVALUACION,
                   L.RUBRO,
                   L.PORCENTAJE,
                   TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ELIMINACION,
                   NVL(p.USUARIO, TO_CHAR(L.ADD_USER_LOG)) AS USUARIO
            FROM SYLLABUS_UAC_EVAL_LOG L
            LEFT JOIN PERSONA p ON L.ADD_USER_LOG = p.PERSONA
            WHERE L.SYLLABUS_UA_CATEDRATICO = :id
              AND L.TIPO_OPERACION = 'D'
            ORDER BY L.ADD_FECHA_LOG DESC";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

function uac_getLogBiblioEv($conn, $intBiblioId) {
    $intBiblioId = intval($intBiblioId);
    $sql = "SELECT L.LOG_ID,
                   TO_CHAR(NVL(L.MOD_FECHA, L.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') AS FECHA_LOG,
                   NVL(p.USUARIO, TO_CHAR(L.ADD_USER_LOG)) AS USUARIO
            FROM SYLLABUS_UAC_BIBLIO_LOG L
            LEFT JOIN PERSONA p ON L.ADD_USER_LOG = p.PERSONA
            WHERE L.SYLLABUS_UAC_BIBLIOGRAFIA = :bid
              AND L.TIPO_OPERACION = 'U'
            ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':bid', $intBiblioId, -1, SQLT_INT);
    oci_execute($stid);
    $arr = [];
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $row['REFERENCIA_COMPLETA'] = uac_getReferenciaBiblioEvLog($conn, $row['LOG_ID']);
        $arr[] = $row;
    }
    oci_free_statement($stid);
    return $arr;
}

function uac_getBiblioEvActivos($conn, $intSyllabusUAC) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $sql = "SELECT b.SYLLABUS_UAC_BIBLIOGRAFIA,
                   TO_CHAR(b.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   NVL(p.USUARIO, TO_CHAR(b.ADD_USER)) AS USUARIO_CREACION
            FROM SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA b
            LEFT JOIN PERSONA p ON b.ADD_USER = p.PERSONA
            WHERE b.SYLLABUS_UA_CATEDRATICO = :id
            ORDER BY b.ADD_FECHA, b.SYLLABUS_UAC_BIBLIOGRAFIA";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $arr = [];
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $row['REFERENCIA_COMPLETA'] = uac_getReferenciaBiblioEv($conn, $row['SYLLABUS_UAC_BIBLIOGRAFIA']);
        $arr[] = $row;
    }
    oci_free_statement($stid);
    return $arr;
}

function uac_getBiblioEvEliminados($conn, $intSyllabusUAC) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $sql = "SELECT L.LOG_ID,
                   L.SYLLABUS_UAC_BIBLIOGRAFIA,
                   TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ELIMINACION,
                   NVL(p.USUARIO, TO_CHAR(L.ADD_USER_LOG)) AS USUARIO
            FROM SYLLABUS_UAC_BIBLIO_LOG L
            LEFT JOIN PERSONA p ON L.ADD_USER_LOG = p.PERSONA
            WHERE L.SYLLABUS_UA_CATEDRATICO = :id
              AND L.TIPO_OPERACION = 'D'
            ORDER BY L.ADD_FECHA_LOG DESC";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $arr = [];
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $row['REFERENCIA_COMPLETA'] = uac_getReferenciaBiblioEvLog($conn, $row['LOG_ID']);
        $arr[] = $row;
    }
    oci_free_statement($stid);
    return $arr;
}

function uac_getLogExp($conn, $intExpId) {
    $intExpId = intval($intExpId);
    $sql = "SELECT L.LOG_ID,
                   L.DESCRIPCION,
                   TO_CHAR(NVL(L.MOD_FECHA, L.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') AS FECHA_LOG,
                   NVL(p.USUARIO, TO_CHAR(L.ADD_USER_LOG)) AS USUARIO
            FROM SYLLABUS_UAC_EXP_LOG L
            LEFT JOIN PERSONA p ON L.ADD_USER_LOG = p.PERSONA
            WHERE L.SYLLABUS_UAC_EXPERIENCIA = :eid
              AND L.TIPO_OPERACION = 'U'
            ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':eid', $intExpId, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

function uac_getExpActivos($conn, $intSyllabusUAC) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $sql = "SELECT e.SYLLABUS_UAC_EXPERIENCIA,
                   e.DESCRIPCION,
                   TO_CHAR(e.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   NVL(p.USUARIO, TO_CHAR(e.ADD_USER)) AS USUARIO_CREACION
            FROM SYLLABUS_UA_CATEDRATICO_EXPERIENCIA e
            LEFT JOIN PERSONA p ON e.ADD_USER = p.PERSONA
            WHERE e.SYLLABUS_UA_CATEDRATICO = :id
            ORDER BY e.ADD_FECHA, e.SYLLABUS_UAC_EXPERIENCIA";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

function uac_getExpEliminados($conn, $intSyllabusUAC) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $sql = "SELECT L.SYLLABUS_UAC_EXPERIENCIA,
                   L.DESCRIPCION,
                   TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ELIMINACION,
                   NVL(p.USUARIO, TO_CHAR(L.ADD_USER_LOG)) AS USUARIO
            FROM SYLLABUS_UAC_EXP_LOG L
            LEFT JOIN PERSONA p ON L.ADD_USER_LOG = p.PERSONA
            WHERE L.SYLLABUS_UA_CATEDRATICO = :id
              AND L.TIPO_OPERACION = 'D'
            ORDER BY L.ADD_FECHA_LOG DESC";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

function uac_bitacora_iconoVer($onclick, $title = null) {
    if ($title === null) {
        $title = 'Ver bit' . "\xE1" . 'cora';
    }
    $title = htmlspecialchars($title, ENT_QUOTES, 'ISO-8859-1');
    return '<button type="button" class="btn-bitacora-icon" title="' . $title . '" onclick="' . $onclick . '">&#128203;</button>';
}

function uac_bitacora_iconoPreview($onclick, $title = 'Ver contenido') {
    $title = htmlspecialchars($title, ENT_QUOTES, 'ISO-8859-1');
    return '<button type="button" class="btn-preview-icon" title="' . $title . '" onclick="' . $onclick . '">&#128065;</button>';
}

function uac_drawBlurBitacoraCampo($conn, $intSyllabusUAC, $strCampo) {
    $arrCampos = uac_bitacora_camposValidos();
    if (!isset($arrCampos[$strCampo])) {
        echo '<p>Campo invalido</p>';
        return;
    }
    $strNombre = $arrCampos[$strCampo];
    $intSyllabusUAC = intval($intSyllabusUAC);

    $sqlMeta = "SELECT TO_CHAR(s.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                       NVL(p1.USUARIO, TO_CHAR(s.ADD_USER)) AS USUARIO_CREACION
                FROM SYLLABUS_UA_CATEDRATICO s
                LEFT JOIN PERSONA p1 ON s.ADD_USER = p1.PERSONA
                WHERE s.SYLLABUS_UA_CATEDRATICO = :id";
    $stid = oci_parse($conn, $sqlMeta);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $rActual = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    $arrLog = uac_getLogCampoClob($conn, $intSyllabusUAC, $strCampo);
    $strCampoJs = htmlspecialchars($strCampo, ENT_QUOTES, 'ISO-8859-1');
    $strNombreJs = htmlspecialchars($strNombre, ENT_QUOTES, 'ISO-8859-1');
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de <?php print $strNombre; ?></h3>
    <?php if ($rActual) { ?>
    <div class="bitacora-meta">
        <strong>Creado por:</strong> <?php print htmlspecialchars($rActual['USUARIO_CREACION'] ?? ''); ?><br>
        <strong>Fecha de creaci&oacute;n:</strong> <?php print htmlspecialchars($rActual['FECHA_CREACION'] ?? ''); ?>
    </div>
    <?php } ?>
    <h4 class="bitacora-subtitle">Historial de modificaciones</h4>
    <?php if (empty($arrLog)) { ?>
        <p class="bitacora-empty"><em>No hay cambios registrados para este campo.</em></p>
    <?php } else { ?>
    <table class="bitacora-table">
        <thead>
            <tr><th>Fecha del cambio</th><th>Realizado por</th><th>Ver</th></tr>
        </thead>
        <tbody>
        <?php
        foreach ($arrLog as $rLog) {
            $intLogId = intval($rLog['LOG_ID']);
            $strFecha = $rLog['ADD_FECHA_LOG'] ?? '';
            if ($strFecha && strpos($strFecha, '/') === false) {
                $strFecha = date('d/m/Y H:i:s', strtotime($strFecha));
            }
            ?>
            <tr>
                <td><?php print htmlspecialchars($strFecha); ?></td>
                <td><?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?></td>
                <td class="bitacora-actions"><?php
                    print uac_bitacora_iconoPreview(
                        "fntVerDetalleLogCampoUAC({$intLogId}, '{$strCampoJs}', '{$strNombreJs}');",
                        'Ver contenido anterior'
                    );
                ?></td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurDetalleLogCampo($conn, $intLogId, $strCampo, $strNombreCampo) {
    $arrCampos = uac_bitacora_camposValidos();
    if (!isset($arrCampos[$strCampo])) {
        echo '<p>Campo invalido</p>';
        return;
    }
    if (!uac_bitacora_esCampoClob($strCampo)) {
        echo '<p>Campo invalido</p>';
        return;
    }
    $intLogId = intval($intLogId);
    $sql = "SELECT L.ADD_FECHA_LOG, NVL(p.USUARIO, TO_CHAR(L.ADD_USER_LOG)) AS USUARIO
            FROM SYLLABUS_UAC_LOG L
            LEFT JOIN PERSONA p ON NVL(L.ADD_USER_LOG, L.ADD_USER) = p.PERSONA
            WHERE L.LOG_ID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intLogId, -1, SQLT_INT);
    oci_execute($stid);
    $rLog = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    if (!$rLog) {
        echo '<p>Registro no encontrado</p>';
        return;
    }
    $strContenido = uac_getClobLog($conn, $intLogId, $strCampo);
    $strFecha = $rLog['ADD_FECHA_LOG'] ?? '';
    if ($strFecha && strpos($strFecha, '/') === false) {
        $strFecha = date('d/m/Y H:i:s', strtotime($strFecha));
    }
    ?>
<div class="bitacora-wrap">
    <div class="bitacora-meta">
        <strong>Fecha del cambio:</strong> <?php print htmlspecialchars($strFecha); ?><br>
        <strong>Realizado por:</strong> <?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?>
    </div>
    <h3 class="bitacora-title"><?php print htmlspecialchars($strNombreCampo); ?></h3>
    <div class="bitacora-content-box">
        <?php print !empty($strContenido) ? $strContenido : '<em>Sin contenido</em>'; ?>
    </div>
</div>
    <?php
}

function uac_drawBlurValorActualCampo($conn, $intSyllabusUAC, $strCampo, $strNombreCampo) {
    $arrCampos = uac_bitacora_camposValidos();
    if (!isset($arrCampos[$strCampo])) {
        echo '<p>Campo invalido</p>';
        return;
    }
    $intSyllabusUAC = intval($intSyllabusUAC);
    $arrLogClob = uac_getLogCampoClob($conn, $intSyllabusUAC, $strCampo);

    $stid = oci_parse($conn,
        "SELECT TO_CHAR(s.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                NVL(p1.USUARIO, TO_CHAR(s.ADD_USER)) AS USUARIO_CREACION
         FROM SYLLABUS_UA_CATEDRATICO s
         LEFT JOIN PERSONA p1 ON s.ADD_USER = p1.PERSONA
         WHERE s.SYLLABUS_UA_CATEDRATICO = :id");
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $rCreacion = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    if (!empty($arrLogClob)) {
        $strFechaActual = $arrLogClob[0]['ADD_FECHA_LOG'] ?? '';
        if ($strFechaActual && strpos($strFechaActual, '/') === false) {
            $strFechaActual = date('d/m/Y H:i:s', strtotime($strFechaActual));
        }
        $strUsuarioActual = $arrLogClob[0]['USUARIO'] ?? '';
    } else {
        $strFechaActual   = $rCreacion['FECHA_CREACION'] ?? '';
        $strUsuarioActual = $rCreacion['USUARIO_CREACION'] ?? '';
    }

    $strContenido = uac_getClobActualCampo($conn, $intSyllabusUAC, $strCampo);
    ?>
<div class="bitacora-wrap">
    <div class="bitacora-meta">
        <strong>Vigente desde:</strong> <?php print htmlspecialchars($strFechaActual); ?><br>
        <strong>Realizado por:</strong> <?php print htmlspecialchars($strUsuarioActual); ?>
    </div>
    <h3 class="bitacora-title"><?php print htmlspecialchars($strNombreCampo); ?></h3>
    <div class="bitacora-content-box">
        <?php print !empty($strContenido) ? $strContenido : '<em>Sin contenido</em>'; ?>
    </div>
</div>
    <?php
}

function uac_drawBlurBitacoraEval($conn, $intEvalId, $intSyllabusUAC) {
    $intEvalId = intval($intEvalId);
    $sql = "SELECT e.RUBRO, e.PORCENTAJE,
                   TO_CHAR(NVL(e.MOD_FECHA, e.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ACTUAL,
                   NVL(p2.USUARIO, p1.USUARIO) AS USUARIO_ACTUAL,
                   TO_CHAR(e.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   p1.USUARIO AS USUARIO_CREACION
            FROM SYLLABUS_UA_CATEDRATICO_EVALUACION e
            LEFT JOIN PERSONA p1 ON e.ADD_USER = p1.PERSONA
            LEFT JOIN PERSONA p2 ON e.MOD_USER = p2.PERSONA
            WHERE e.SYLLABUS_UAC_EVALUACION = :eid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':eid', $intEvalId, -1, SQLT_INT);
    oci_execute($stid);
    $rEval = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    $arrLog = uac_getLogEval($conn, $intEvalId);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de actividad de evaluaci&oacute;n</h3>
    <?php if ($rEval) { ?>
    <div class="bitacora-meta">
        <strong>Creado por:</strong> <?php print htmlspecialchars($rEval['USUARIO_CREACION'] ?? ''); ?><br>
        <strong>Fecha de creaci&oacute;n:</strong> <?php print htmlspecialchars($rEval['FECHA_CREACION'] ?? ''); ?>
    </div>
    <h4 class="bitacora-subtitle">Valor actual</h4>
    <table class="bitacora-table">
        <thead>
            <tr><th>Fecha</th><th>Usuario</th><th>Rubro</th><th>%</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><?php print htmlspecialchars($rEval['FECHA_ACTUAL'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rEval['USUARIO_ACTUAL'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rEval['RUBRO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rEval['PORCENTAJE'] ?? ''); ?>%</td>
            </tr>
        </tbody>
    </table>
    <?php } ?>
    <h4 class="bitacora-subtitle">Historial de modificaciones</h4>
    <?php if (empty($arrLog)) { ?>
        <p class="bitacora-empty"><em>No hay cambios registrados.</em></p>
    <?php } else { ?>
    <table class="bitacora-table">
        <thead>
            <tr><th>Fecha</th><th>Usuario</th><th>Rubro</th><th>%</th></tr>
        </thead>
        <tbody>
        <?php foreach ($arrLog as $rLog) { ?>
            <tr>
                <td><?php print htmlspecialchars($rLog['FECHA_LOG'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['RUBRO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['PORCENTAJE'] ?? ''); ?>%</td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraEvalEliminado($conn, $intEvalId) {
    $intEvalId = intval($intEvalId);
    $sql = "SELECT L.RUBRO, L.PORCENTAJE,
                   TO_CHAR(L.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   p1.USUARIO AS USUARIO_CREACION,
                   TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ELIMINACION,
                   p2.USUARIO AS USUARIO_ELIMINACION
            FROM SYLLABUS_UAC_EVAL_LOG L
            LEFT JOIN PERSONA p1 ON L.ADD_USER = p1.PERSONA
            LEFT JOIN PERSONA p2 ON L.ADD_USER_LOG = p2.PERSONA
            WHERE L.SYLLABUS_UAC_EVALUACION = :eid AND L.TIPO_OPERACION = 'D'
            ORDER BY L.ADD_FECHA_LOG DESC
            FETCH FIRST 1 ROW ONLY";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':eid', $intEvalId, -1, SQLT_INT);
    oci_execute($stid);
    $rEval = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    $arrLog = uac_getLogEval($conn, $intEvalId);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de actividad de evaluaci&oacute;n (eliminada)</h3>
    <?php if ($rEval) { ?>
    <div class="bitacora-meta">
        <strong>Creado por:</strong> <?php print htmlspecialchars($rEval['USUARIO_CREACION'] ?? ''); ?><br>
        <strong>Fecha de creaci&oacute;n:</strong> <?php print htmlspecialchars($rEval['FECHA_CREACION'] ?? ''); ?>
    </div>
    <div class="bitacora-meta bitacora-deleted">
        <strong>Eliminado por:</strong> <?php print htmlspecialchars($rEval['USUARIO_ELIMINACION'] ?? ''); ?><br>
        <strong>Fecha de eliminaci&oacute;n:</strong> <?php print htmlspecialchars($rEval['FECHA_ELIMINACION'] ?? ''); ?>
    </div>
    <h4 class="bitacora-subtitle">&Uacute;ltima versi&oacute;n (antes de eliminar)</h4>
    <table class="bitacora-table">
        <thead><tr><th>Rubro</th><th>%</th></tr></thead>
        <tbody>
            <tr>
                <td><?php print htmlspecialchars($rEval['RUBRO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rEval['PORCENTAJE'] ?? ''); ?>%</td>
            </tr>
        </tbody>
    </table>
    <?php } else { ?>
        <p class="bitacora-empty"><em>No se encontr&oacute; registro de eliminaci&oacute;n.</em></p>
    <?php } ?>
    <h4 class="bitacora-subtitle">Historial de modificaciones</h4>
    <?php if (empty($arrLog)) { ?>
        <p class="bitacora-empty"><em>No hay cambios registrados antes de eliminar.</em></p>
    <?php } else { ?>
    <table class="bitacora-table">
        <thead>
            <tr><th>Fecha</th><th>Usuario</th><th>Rubro</th><th>%</th></tr>
        </thead>
        <tbody>
        <?php foreach ($arrLog as $rLog) { ?>
            <tr>
                <td><?php print htmlspecialchars($rLog['FECHA_LOG'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['RUBRO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['PORCENTAJE'] ?? ''); ?>%</td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraTodosEval($conn, $intSyllabusUAC) {
    $arrActivos = uac_getEvalActivos($conn, $intSyllabusUAC);
    $arrElim = uac_getEvalEliminados($conn, $intSyllabusUAC);
    $intSid = intval($intSyllabusUAC);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de evaluaci&oacute;n del curso</h3>
    <?php if (count($arrActivos) > 0) { ?>
    <h4 class="bitacora-subtitle">Actividades actuales</h4>
    <table class="bitacora-table">
        <thead>
            <tr><th>Fecha creaci&oacute;n</th><th>Usuario</th><th>Rubro</th><th>%</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($arrActivos as $r) {
            $eid = intval($r['SYLLABUS_UAC_EVALUACION']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($r['FECHA_CREACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['USUARIO_CREACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['RUBRO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['PORCENTAJE'] ?? ''); ?>%</td>
                <td class="bitacora-actions"><?php
                    print uac_bitacora_iconoVer("fntMostrarBitacoraEval({$eid});", 'Ver bit' . "\xE1" . 'cora de esta actividad');
                ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } else { ?>
        <p class="bitacora-empty"><em>No hay actividades de evaluaci&oacute;n registradas.</em></p>
    <?php } ?>
    <?php if (count($arrElim) > 0) { ?>
    <h4 class="bitacora-subtitle">Actividades eliminadas</h4>
    <table class="bitacora-table">
        <thead>
            <tr><th>Fecha eliminaci&oacute;n</th><th>Usuario</th><th>Rubro</th><th>%</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($arrElim as $r) {
            $eid = intval($r['SYLLABUS_UAC_EVALUACION']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($r['FECHA_ELIMINACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['USUARIO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['RUBRO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['PORCENTAJE'] ?? ''); ?>%</td>
                <td class="bitacora-actions"><?php
                    print uac_bitacora_iconoVer("fntMostrarBitacoraEvalEliminado({$eid});", 'Ver bit' . "\xE1" . 'cora eliminada');
                ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraBiblioEv($conn, $intBiblioId, $intSyllabusUAC) {
    $intBiblioId = intval($intBiblioId);
    $sql = "SELECT TO_CHAR(NVL(b.MOD_FECHA, b.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ACTUAL,
                   NVL(p2.USUARIO, p1.USUARIO) AS USUARIO_ACTUAL,
                   TO_CHAR(b.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   p1.USUARIO AS USUARIO_CREACION
            FROM SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA b
            LEFT JOIN PERSONA p1 ON b.ADD_USER = p1.PERSONA
            LEFT JOIN PERSONA p2 ON b.MOD_USER = p2.PERSONA
            WHERE b.SYLLABUS_UAC_BIBLIOGRAFIA = :bid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':bid', $intBiblioId, -1, SQLT_INT);
    oci_execute($stid);
    $rBib = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    if ($rBib) {
        $rBib['REFERENCIA_COMPLETA'] = uac_getReferenciaBiblioEv($conn, $intBiblioId);
    }
    $arrLog = uac_getLogBiblioEv($conn, $intBiblioId);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de referencia bibliogr&aacute;fica</h3>
    <?php if ($rBib) { ?>
    <div class="bitacora-meta">
        <strong>Creado por:</strong> <?php print htmlspecialchars($rBib['USUARIO_CREACION'] ?? ''); ?><br>
        <strong>Fecha de creaci&oacute;n:</strong> <?php print htmlspecialchars($rBib['FECHA_CREACION'] ?? ''); ?>
    </div>
    <h4 class="bitacora-subtitle">Valor actual</h4>
    <table class="bitacora-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Referencia</th></tr></thead>
        <tbody>
            <tr>
                <td><?php print htmlspecialchars($rBib['FECHA_ACTUAL'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rBib['USUARIO_ACTUAL'] ?? ''); ?></td>
                <td style="text-align:left;"><?php print uac_renderReferenciaBiblioVista($rBib['REFERENCIA_COMPLETA'] ?? ''); ?></td>
            </tr>
        </tbody>
    </table>
    <?php } ?>
    <h4 class="bitacora-subtitle">Historial de modificaciones</h4>
    <?php if (empty($arrLog)) { ?>
        <p class="bitacora-empty"><em>No hay cambios registrados.</em></p>
    <?php } else { ?>
    <table class="bitacora-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Referencia</th></tr></thead>
        <tbody>
        <?php foreach ($arrLog as $rLog) { ?>
            <tr>
                <td><?php print htmlspecialchars($rLog['FECHA_LOG'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?></td>
                <td style="text-align:left;"><?php print uac_renderReferenciaBiblioVista($rLog['REFERENCIA_COMPLETA'] ?? ''); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraBiblioEvEliminado($conn, $intBiblioId) {
    $intBiblioId = intval($intBiblioId);
    $sql = "SELECT L.LOG_ID,
                   TO_CHAR(L.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   p1.USUARIO AS USUARIO_CREACION,
                   TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ELIMINACION,
                   p2.USUARIO AS USUARIO_ELIMINACION
            FROM SYLLABUS_UAC_BIBLIO_LOG L
            LEFT JOIN PERSONA p1 ON L.ADD_USER = p1.PERSONA
            LEFT JOIN PERSONA p2 ON L.ADD_USER_LOG = p2.PERSONA
            WHERE L.SYLLABUS_UAC_BIBLIOGRAFIA = :bid AND L.TIPO_OPERACION = 'D'
            ORDER BY L.ADD_FECHA_LOG DESC
            FETCH FIRST 1 ROW ONLY";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':bid', $intBiblioId, -1, SQLT_INT);
    oci_execute($stid);
    $rBib = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    if ($rBib) {
        $rBib['REFERENCIA_COMPLETA'] = uac_getReferenciaBiblioEvLog($conn, $rBib['LOG_ID']);
    }

    $arrLog = uac_getLogBiblioEv($conn, $intBiblioId);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de referencia bibliogr&aacute;fica (eliminada)</h3>
    <?php if ($rBib) { ?>
    <div class="bitacora-meta">
        <strong>Creado por:</strong> <?php print htmlspecialchars($rBib['USUARIO_CREACION'] ?? ''); ?><br>
        <strong>Fecha de creaci&oacute;n:</strong> <?php print htmlspecialchars($rBib['FECHA_CREACION'] ?? ''); ?>
    </div>
    <div class="bitacora-meta bitacora-deleted">
        <strong>Eliminado por:</strong> <?php print htmlspecialchars($rBib['USUARIO_ELIMINACION'] ?? ''); ?><br>
        <strong>Fecha de eliminaci&oacute;n:</strong> <?php print htmlspecialchars($rBib['FECHA_ELIMINACION'] ?? ''); ?>
    </div>
    <h4 class="bitacora-subtitle">&Uacute;ltima versi&oacute;n (antes de eliminar)</h4>
    <div class="bitacora-content-box"><?php print uac_renderReferenciaBiblioVista($rBib['REFERENCIA_COMPLETA'] ?? ''); ?></div>
    <?php } else { ?>
        <p class="bitacora-empty"><em>No se encontr&oacute; registro de eliminaci&oacute;n.</em></p>
    <?php } ?>
    <h4 class="bitacora-subtitle">Historial de modificaciones</h4>
    <?php if (empty($arrLog)) { ?>
        <p class="bitacora-empty"><em>No hay cambios registrados antes de eliminar.</em></p>
    <?php } else { ?>
    <table class="bitacora-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Referencia</th></tr></thead>
        <tbody>
        <?php foreach ($arrLog as $rLog) { ?>
            <tr>
                <td><?php print htmlspecialchars($rLog['FECHA_LOG'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?></td>
                <td style="text-align:left;"><?php print uac_renderReferenciaBiblioVista($rLog['REFERENCIA_COMPLETA'] ?? ''); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraTodosBiblioEv($conn, $intSyllabusUAC) {
    $arrActivos = uac_getBiblioEvActivos($conn, $intSyllabusUAC);
    $arrElim = uac_getBiblioEvEliminados($conn, $intSyllabusUAC);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de bibliograf&iacute;a evolutiva</h3>
    <?php if (count($arrActivos) > 0) { ?>
    <h4 class="bitacora-subtitle">Referencias actuales</h4>
    <table class="bitacora-table">
        <thead><tr><th>Fecha creaci&oacute;n</th><th>Usuario</th><th>Referencia</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($arrActivos as $r) {
            $bid = intval($r['SYLLABUS_UAC_BIBLIOGRAFIA']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($r['FECHA_CREACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['USUARIO_CREACION'] ?? ''); ?></td>
                <td style="text-align:left;"><?php print uac_renderReferenciaBiblioVista($r['REFERENCIA_COMPLETA'] ?? ''); ?></td>
                <td class="bitacora-actions"><?php
                    print uac_bitacora_iconoVer("fntMostrarBitacoraBiblioEv({$bid});", 'Ver bit' . "\xE1" . 'cora');
                ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } else { ?>
        <p class="bitacora-empty"><em>No hay referencias registradas.</em></p>
    <?php } ?>
    <?php if (count($arrElim) > 0) { ?>
    <h4 class="bitacora-subtitle">Referencias eliminadas</h4>
    <table class="bitacora-table">
        <thead><tr><th>Fecha eliminaci&oacute;n</th><th>Usuario</th><th>Referencia</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($arrElim as $r) {
            $bid = intval($r['SYLLABUS_UAC_BIBLIOGRAFIA']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($r['FECHA_ELIMINACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['USUARIO'] ?? ''); ?></td>
                <td style="text-align:left;"><?php print uac_renderReferenciaBiblioVista($r['REFERENCIA_COMPLETA'] ?? ''); ?></td>
                <td class="bitacora-actions"><?php
                    print uac_bitacora_iconoVer("fntMostrarBitacoraBiblioEvEliminado({$bid});", 'Ver bit' . "\xE1" . 'cora');
                ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraExp($conn, $intExpId, $intSyllabusUAC) {
    $intExpId = intval($intExpId);
    $sql = "SELECT e.DESCRIPCION,
                   TO_CHAR(NVL(e.MOD_FECHA, e.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ACTUAL,
                   NVL(p2.USUARIO, p1.USUARIO) AS USUARIO_ACTUAL,
                   TO_CHAR(e.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   p1.USUARIO AS USUARIO_CREACION
            FROM SYLLABUS_UA_CATEDRATICO_EXPERIENCIA e
            LEFT JOIN PERSONA p1 ON e.ADD_USER = p1.PERSONA
            LEFT JOIN PERSONA p2 ON e.MOD_USER = p2.PERSONA
            WHERE e.SYLLABUS_UAC_EXPERIENCIA = :eid";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':eid', $intExpId, -1, SQLT_INT);
    oci_execute($stid);
    $rExp = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    $arrLog = uac_getLogExp($conn, $intExpId);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de experiencia principal</h3>
    <?php if ($rExp) { ?>
    <div class="bitacora-meta">
        <strong>Creado por:</strong> <?php print htmlspecialchars($rExp['USUARIO_CREACION'] ?? ''); ?><br>
        <strong>Fecha de creaci&oacute;n:</strong> <?php print htmlspecialchars($rExp['FECHA_CREACION'] ?? ''); ?>
    </div>
    <h4 class="bitacora-subtitle">Valor actual</h4>
    <table class="bitacora-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Descripci&oacute;n</th></tr></thead>
        <tbody>
            <tr>
                <td><?php print htmlspecialchars($rExp['FECHA_ACTUAL'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rExp['USUARIO_ACTUAL'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rExp['DESCRIPCION'] ?? ''); ?></td>
            </tr>
        </tbody>
    </table>
    <?php } ?>
    <h4 class="bitacora-subtitle">Historial de modificaciones</h4>
    <?php if (empty($arrLog)) { ?>
        <p class="bitacora-empty"><em>No hay cambios registrados.</em></p>
    <?php } else { ?>
    <table class="bitacora-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Descripci&oacute;n</th></tr></thead>
        <tbody>
        <?php foreach ($arrLog as $rLog) { ?>
            <tr>
                <td><?php print htmlspecialchars($rLog['FECHA_LOG'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['DESCRIPCION'] ?? ''); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraExpEliminado($conn, $intExpId) {
    $intExpId = intval($intExpId);
    $sql = "SELECT L.DESCRIPCION,
                   TO_CHAR(L.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   p1.USUARIO AS USUARIO_CREACION,
                   TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ELIMINACION,
                   p2.USUARIO AS USUARIO_ELIMINACION
            FROM SYLLABUS_UAC_EXP_LOG L
            LEFT JOIN PERSONA p1 ON L.ADD_USER = p1.PERSONA
            LEFT JOIN PERSONA p2 ON L.ADD_USER_LOG = p2.PERSONA
            WHERE L.SYLLABUS_UAC_EXPERIENCIA = :eid AND L.TIPO_OPERACION = 'D'
            ORDER BY L.ADD_FECHA_LOG DESC
            FETCH FIRST 1 ROW ONLY";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':eid', $intExpId, -1, SQLT_INT);
    oci_execute($stid);
    $rExp = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    $arrLog = uac_getLogExp($conn, $intExpId);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de experiencia principal (eliminada)</h3>
    <?php if ($rExp) { ?>
    <div class="bitacora-meta">
        <strong>Creado por:</strong> <?php print htmlspecialchars($rExp['USUARIO_CREACION'] ?? ''); ?><br>
        <strong>Fecha de creaci&oacute;n:</strong> <?php print htmlspecialchars($rExp['FECHA_CREACION'] ?? ''); ?>
    </div>
    <div class="bitacora-meta bitacora-deleted">
        <strong>Eliminado por:</strong> <?php print htmlspecialchars($rExp['USUARIO_ELIMINACION'] ?? ''); ?><br>
        <strong>Fecha de eliminaci&oacute;n:</strong> <?php print htmlspecialchars($rExp['FECHA_ELIMINACION'] ?? ''); ?>
    </div>
    <h4 class="bitacora-subtitle">&Uacute;ltimo valor antes de eliminar</h4>
    <table class="bitacora-table">
        <thead><tr><th>Descripci&oacute;n</th></tr></thead>
        <tbody>
            <tr><td><?php print htmlspecialchars($rExp['DESCRIPCION'] ?? ''); ?></td></tr>
        </tbody>
    </table>
    <?php } ?>
    <h4 class="bitacora-subtitle">Historial de modificaciones previas</h4>
    <?php if (empty($arrLog)) { ?>
        <p class="bitacora-empty"><em>No hay cambios registrados.</em></p>
    <?php } else { ?>
    <table class="bitacora-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Descripci&oacute;n</th></tr></thead>
        <tbody>
        <?php foreach ($arrLog as $rLog) { ?>
            <tr>
                <td><?php print htmlspecialchars($rLog['FECHA_LOG'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['DESCRIPCION'] ?? ''); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraTodosExp($conn, $intSyllabusUAC) {
    $arrActivos = uac_getExpActivos($conn, $intSyllabusUAC);
    $arrElim = uac_getExpEliminados($conn, $intSyllabusUAC);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de experiencias principales</h3>
    <?php if (count($arrActivos) > 0) { ?>
    <h4 class="bitacora-subtitle">Experiencias actuales</h4>
    <table class="bitacora-table">
        <thead><tr><th>Fecha creaci&oacute;n</th><th>Usuario</th><th>Descripci&oacute;n</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($arrActivos as $r) {
            $eid = intval($r['SYLLABUS_UAC_EXPERIENCIA']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($r['FECHA_CREACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['USUARIO_CREACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['DESCRIPCION'] ?? ''); ?></td>
                <td class="bitacora-actions"><?php
                    print uac_bitacora_iconoVer("fntMostrarBitacoraExp({$eid});", 'Ver bit' . "\xE1" . 'cora');
                ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } else { ?>
        <p class="bitacora-empty"><em>No hay experiencias registradas.</em></p>
    <?php } ?>
    <?php if (count($arrElim) > 0) { ?>
    <h4 class="bitacora-subtitle">Experiencias eliminadas</h4>
    <table class="bitacora-table">
        <thead><tr><th>Fecha eliminaci&oacute;n</th><th>Usuario</th><th>Descripci&oacute;n</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($arrElim as $r) {
            $eid = intval($r['SYLLABUS_UAC_EXPERIENCIA']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($r['FECHA_ELIMINACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['USUARIO'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['DESCRIPCION'] ?? ''); ?></td>
                <td class="bitacora-actions"><?php
                    print uac_bitacora_iconoVer("fntMostrarBitacoraExpEliminado({$eid});", 'Ver bit' . "\xE1" . 'cora');
                ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_bitacora_verificarCronograma($conn, $intCronoId, $intSyllabusUAC) {
    $intCronoId     = intval($intCronoId);
    $intSyllabusUAC = intval($intSyllabusUAC);
    if ($intCronoId <= 0 || $intSyllabusUAC <= 0) {
        return false;
    }
    $sql = "SELECT 1 AS OK
            FROM SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
            WHERE SYLLABUS_UAC_CRONOGRAMA  = :p_crono_id
              AND SYLLABUS_UA_CATEDRATICO  = :p_syl_uac";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':p_crono_id', $intCronoId,     -1, SQLT_INT);
    oci_bind_by_name($stid, ':p_syl_uac',  $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    return !empty($row);
}

function uac_getLogCronoAdjunto($conn, $intCronoId) {
    $intCronoId = intval($intCronoId);
    $sql = "SELECT L.LOG_ID,
                   L.PATH_ARCHIVO,
                   L.NOMBRE_ARCHIVO,
                   L.ACTIVO,
                   TO_CHAR(NVL(L.MOD_FECHA, L.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') AS FECHA_LOG,
                   NVL(p.USUARIO, TO_CHAR(L.ADD_USER_LOG)) AS USUARIO,
                   L.TIPO_OPERACION
            FROM SYLLABUS_UAC_CRONO_LOG L
            LEFT JOIN PERSONA p ON L.ADD_USER_LOG = p.PERSONA
            WHERE L.SYLLABUS_UAC_CRONOGRAMA = :p_crono_id
            ORDER BY L.ADD_FECHA_LOG DESC, L.LOG_ID DESC";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':p_crono_id', $intCronoId, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

function uac_getCronoActivos($conn, $intSyllabusUAC) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $sql = "SELECT c.SYLLABUS_UAC_CRONOGRAMA,
                   c.NOMBRE_ARCHIVO,
                   c.ACTIVO,
                   TO_CHAR(c.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   NVL(p.USUARIO, TO_CHAR(c.ADD_USER)) AS USUARIO_CREACION
            FROM SYLLABUS_UA_CATEDRATICO_CRONOGRAMA c
            LEFT JOIN PERSONA p ON c.ADD_USER = p.PERSONA
            WHERE c.SYLLABUS_UA_CATEDRATICO = :id
            ORDER BY c.ADD_FECHA, c.SYLLABUS_UAC_CRONOGRAMA";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

function uac_getCronoEliminados($conn, $intSyllabusUAC) {
    $intSyllabusUAC = intval($intSyllabusUAC);
    $sql = "SELECT L.LOG_ID,
                   L.SYLLABUS_UAC_CRONOGRAMA,
                   L.NOMBRE_ARCHIVO,
                   TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ELIMINACION,
                   NVL(p.USUARIO, TO_CHAR(L.ADD_USER_LOG)) AS USUARIO
            FROM SYLLABUS_UAC_CRONO_LOG L
            LEFT JOIN PERSONA p ON L.ADD_USER_LOG = p.PERSONA
            WHERE L.SYLLABUS_UA_CATEDRATICO = :id
              AND L.TIPO_OPERACION = 'D'
            ORDER BY L.ADD_FECHA_LOG DESC";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $intSyllabusUAC, -1, SQLT_INT);
    oci_execute($stid);
    $arr = uac_bitacora_fetchAll($stid);
    oci_free_statement($stid);
    return $arr;
}

/**
 * Filtra el array de log de cronograma para mostrar solo las entradas donde
 * realmente cambió PATH_ARCHIVO o ACTIVO respecto al estado que le siguió.
 * El array $arrLog debe estar en orden DESC (más reciente primero).
 * $strPathSig, $strActivoSig = valores del estado inmediatamente posterior
 *   al log[0], que puede ser el registro actual (si no está eliminado) o
 *   simplemente null/null para mostrar siempre el primer elemento.
 */
function uac_filtraCronogramaLog($arrLog, $strPathSig = null, $strActivoSig = null) {
    $arrFiltrado = [];
    $count = count($arrLog);
    for ($i = 0; $i < $count; $i++) {
        $strPathCurr   = trim($arrLog[$i]['PATH_ARCHIVO']   ?? '');
        $strActivoCurr = trim($arrLog[$i]['ACTIVO']         ?? 'Y');

        if ($i === 0) {
            if ($strPathSig === null) {
                $arrFiltrado[] = $arrLog[$i];
                continue;
            }
            $strPathCmp   = trim((string) $strPathSig);
            $strActivoCmp = trim((string) $strActivoSig);
        } else {
            $strPathCmp   = trim($arrLog[$i - 1]['PATH_ARCHIVO'] ?? '');
            $strActivoCmp = trim($arrLog[$i - 1]['ACTIVO']       ?? 'Y');
        }

        if ($strPathCurr !== $strPathCmp || $strActivoCurr !== $strActivoCmp) {
            $arrFiltrado[] = $arrLog[$i];
        }
    }
    return $arrFiltrado;
}

function uac_drawBlurBitacoraCronoAdjunto($conn, $intCronoId, $intSyllabusUAC) {
    $intCronoId = intval($intCronoId);
    $sql = "SELECT c.NOMBRE_ARCHIVO,
                   c.PATH_ARCHIVO,
                   c.ACTIVO,
                   TO_CHAR(NVL(c.MOD_FECHA, c.ADD_FECHA), 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ACTUAL,
                   NVL(p2.USUARIO, p1.USUARIO) AS USUARIO_ACTUAL,
                   TO_CHAR(c.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   p1.USUARIO AS USUARIO_CREACION
            FROM SYLLABUS_UA_CATEDRATICO_CRONOGRAMA c
            LEFT JOIN PERSONA p1 ON c.ADD_USER = p1.PERSONA
            LEFT JOIN PERSONA p2 ON c.MOD_USER = p2.PERSONA
            WHERE c.SYLLABUS_UAC_CRONOGRAMA = :p_crono_id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':p_crono_id', $intCronoId, -1, SQLT_INT);
    oci_execute($stid);
    $rCrono = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    $arrLog = uac_getLogCronoAdjunto($conn, $intCronoId);
    $arrLogFiltrado = uac_filtraCronogramaLog(
        $arrLog,
        $rCrono ? ($rCrono['PATH_ARCHIVO'] ?? '') : null,
        $rCrono ? ($rCrono['ACTIVO']       ?? 'Y') : null
    );
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de cronograma adjunto</h3>
    <?php if ($rCrono) { ?>
    <div class="bitacora-meta">
        <strong>Creado por:</strong> <?php print htmlspecialchars($rCrono['USUARIO_CREACION'] ?? ''); ?><br>
        <strong>Fecha de creaci&oacute;n:</strong> <?php print htmlspecialchars($rCrono['FECHA_CREACION'] ?? ''); ?>
    </div>
    <h4 class="bitacora-subtitle">Archivo actual</h4>
    <table class="bitacora-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Archivo</th><th>Estado</th></tr></thead>
        <tbody>
            <tr>
                <td><?php print htmlspecialchars($rCrono['FECHA_ACTUAL'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rCrono['USUARIO_ACTUAL'] ?? ''); ?></td>
                <td><?php print uac_bitacora_botonDescargaCronoId($rCrono['NOMBRE_ARCHIVO'] ?? '', $intCronoId); ?></td>
                <td><?php print ($rCrono['ACTIVO'] === 'N') ? 'Inactivo' : 'Activo'; ?></td>
            </tr>
        </tbody>
    </table>
    <?php } ?>
    <h4 class="bitacora-subtitle">Historial de modificaciones</h4>
    <?php if (empty($arrLogFiltrado)) { ?>
        <p class="bitacora-empty"><em>No hay cambios registrados.</em></p>
    <?php } else { ?>
    <table class="bitacora-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Archivo</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($arrLogFiltrado as $rLog) {
            $intLogId = intval($rLog['LOG_ID']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($rLog['FECHA_LOG'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?></td>
                <td><?php print uac_bitacora_botonDescargaCronograma($rLog['PATH_ARCHIVO'] ?? '', $intLogId, $rLog['NOMBRE_ARCHIVO'] ?? ''); ?></td>
                <td><?php print ($rLog['ACTIVO'] === 'N') ? 'Inactivo' : 'Activo'; ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraCronoEliminado($conn, $intCronoId) {
    $intCronoId = intval($intCronoId);
    $sql = "SELECT L.NOMBRE_ARCHIVO,
                   L.PATH_ARCHIVO,
                   TO_CHAR(L.ADD_FECHA, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_CREACION,
                   p1.USUARIO AS USUARIO_CREACION,
                   TO_CHAR(L.ADD_FECHA_LOG, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_ELIMINACION,
                   p2.USUARIO AS USUARIO_ELIMINACION
            FROM SYLLABUS_UAC_CRONO_LOG L
            LEFT JOIN PERSONA p1 ON L.ADD_USER = p1.PERSONA
            LEFT JOIN PERSONA p2 ON L.ADD_USER_LOG = p2.PERSONA
            WHERE L.SYLLABUS_UAC_CRONOGRAMA = :p_crono_id AND L.TIPO_OPERACION = 'D'
            ORDER BY L.ADD_FECHA_LOG DESC
            FETCH FIRST 1 ROW ONLY";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':p_crono_id', $intCronoId, -1, SQLT_INT);
    oci_execute($stid);
    $rCrono = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    $arrLog = uac_getLogCronoAdjunto($conn, $intCronoId);
    // Para eliminados: el log[0] es la entrada del DELETE; filtrar desde log[1] en adelante
    $arrLogHistorial = array_slice($arrLog, 1);
    $arrLogFiltrado  = uac_filtraCronogramaLog(
        $arrLogHistorial,
        isset($arrLog[0]) ? ($arrLog[0]['PATH_ARCHIVO'] ?? '') : null,
        isset($arrLog[0]) ? ($arrLog[0]['ACTIVO']       ?? 'Y') : null
    );
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de cronograma (eliminado)</h3>
    <?php if ($rCrono) { ?>
    <div class="bitacora-meta">
        <strong>Creado por:</strong> <?php print htmlspecialchars($rCrono['USUARIO_CREACION'] ?? ''); ?><br>
        <strong>Fecha de creaci&oacute;n:</strong> <?php print htmlspecialchars($rCrono['FECHA_CREACION'] ?? ''); ?>
    </div>
    <div class="bitacora-meta bitacora-deleted">
        <strong>Eliminado por:</strong> <?php print htmlspecialchars($rCrono['USUARIO_ELIMINACION'] ?? ''); ?><br>
        <strong>Fecha de eliminaci&oacute;n:</strong> <?php print htmlspecialchars($rCrono['FECHA_ELIMINACION'] ?? ''); ?>
    </div>
    <h4 class="bitacora-subtitle">&Uacute;ltima versi&oacute;n (antes de eliminar)</h4>
    <table class="bitacora-table">
        <thead><tr><th>Archivo</th></tr></thead>
        <tbody>
            <tr>
                <td><?php
                    $intLastLog = 0;
                    foreach ($arrLog as $l) { $intLastLog = intval($l['LOG_ID']); break; }
                    print uac_bitacora_botonDescargaCronograma($rCrono['PATH_ARCHIVO'] ?? '', $intLastLog, $rCrono['NOMBRE_ARCHIVO'] ?? '');
                ?></td>
            </tr>
        </tbody>
    </table>
    <?php } else { ?>
        <p class="bitacora-empty"><em>No se encontr&oacute; registro de eliminaci&oacute;n.</em></p>
    <?php } ?>
    <h4 class="bitacora-subtitle">Historial de versiones anteriores</h4>
    <?php if (empty($arrLogFiltrado)) { ?>
        <p class="bitacora-empty"><em>No hay cambios registrados antes de eliminar.</em></p>
    <?php } else { ?>
    <table class="bitacora-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Archivo</th></tr></thead>
        <tbody>
        <?php foreach ($arrLogFiltrado as $rLog) {
            $intLogId = intval($rLog['LOG_ID']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($rLog['FECHA_LOG'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($rLog['USUARIO'] ?? ''); ?></td>
                <td><?php print uac_bitacora_botonDescargaCronograma($rLog['PATH_ARCHIVO'] ?? '', $intLogId, $rLog['NOMBRE_ARCHIVO'] ?? ''); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_drawBlurBitacoraTodosCronogramas($conn, $intSyllabusUAC) {
    $arrActivos = uac_getCronoActivos($conn, $intSyllabusUAC);
    $arrElim    = uac_getCronoEliminados($conn, $intSyllabusUAC);
    ?>
<div class="bitacora-wrap">
    <h3 class="bitacora-title">Bit&aacute;cora de cronograma de actividades</h3>
    <?php if (count($arrActivos) > 0) { ?>
    <h4 class="bitacora-subtitle">Cronogramas registrados</h4>
    <table class="bitacora-table">
        <thead><tr><th>Fecha creaci&oacute;n</th><th>Usuario</th><th>Archivo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($arrActivos as $r) {
            $cid = intval($r['SYLLABUS_UAC_CRONOGRAMA']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($r['FECHA_CREACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['USUARIO_CREACION'] ?? ''); ?></td>
                <td><?php print uac_bitacora_botonDescargaCronoId($r['NOMBRE_ARCHIVO'] ?? '', $cid); ?></td>
                <td><?php print ($r['ACTIVO'] === 'N') ? 'Inactivo' : 'Activo'; ?></td>
                <td class="bitacora-actions"><?php
                    print uac_bitacora_iconoVer("fntMostrarBitacoraCronoAdjunto({$cid});", 'Ver bit' . "\xE1" . 'cora de este cronograma');
                ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } else { ?>
        <p class="bitacora-empty"><em>No hay cronogramas registrados.</em></p>
    <?php } ?>
    <?php if (count($arrElim) > 0) { ?>
    <h4 class="bitacora-subtitle">Cronogramas eliminados</h4>
    <table class="bitacora-table">
        <thead><tr><th>Fecha eliminaci&oacute;n</th><th>Usuario</th><th>Archivo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($arrElim as $r) {
            $intLogId  = intval($r['LOG_ID']);
            $cidElim   = intval($r['SYLLABUS_UAC_CRONOGRAMA']);
            ?>
            <tr>
                <td><?php print htmlspecialchars($r['FECHA_ELIMINACION'] ?? ''); ?></td>
                <td><?php print htmlspecialchars($r['USUARIO'] ?? ''); ?></td>
                <td><?php print uac_bitacora_botonDescargaCronograma('', $intLogId, $r['NOMBRE_ARCHIVO'] ?? ''); ?></td>
                <td class="bitacora-actions"><?php
                    print uac_bitacora_iconoVer("fntMostrarBitacoraCronoEliminado({$cidElim});", 'Ver bit' . "\xE1" . 'cora eliminada');
                ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
    <?php
}

function uac_bitacora_handleAjax($conn) {
    $intCimp = isset($_POST['cimp']) ? intval($_POST['cimp']) : 0;
    $intSyllabusUAC = isset($_POST['syllabus_uac']) ? intval($_POST['syllabus_uac']) : 0;

    $boolBitacoraPost = isset($_POST['drawBlurBitacoraCampoUAC'])
        || isset($_POST['drawBlurDetalleLogCampoUAC'])
        || isset($_POST['drawBlurValorActualCampoUAC'])
        || isset($_POST['drawBlurBitacoraTodosEval'])
        || isset($_POST['drawBlurBitacoraEval'])
        || isset($_POST['drawBlurBitacoraEvalEliminado'])
        || isset($_POST['drawBlurBitacoraTodosBiblioEv'])
        || isset($_POST['drawBlurBitacoraBiblioEv'])
        || isset($_POST['drawBlurBitacoraBiblioEvEliminado'])
        || isset($_POST['drawBlurBitacoraTodosExp'])
        || isset($_POST['drawBlurBitacoraExp'])
        || isset($_POST['drawBlurBitacoraExpEliminado'])
        || isset($_POST['drawBlurBitacoraTodosCronogramas'])
        || isset($_POST['drawBlurBitacoraCronoAdjunto'])
        || isset($_POST['drawBlurBitacoraCronoEliminado']);

    if (!$boolBitacoraPost) {
        return;
    }

    header('Content-Type: text/html; charset=windows-1252');

    if (isset($_POST['drawBlurBitacoraCampoUAC'])) {
        $strCampo = isset($_POST['campo']) ? $_POST['campo'] : '';
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraCampo($conn, $intSyllabusUAC, $strCampo);
        die();
    }

    if (isset($_POST['drawBlurDetalleLogCampoUAC'])) {
        $intLogId = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
        $strCampo = isset($_POST['campo']) ? $_POST['campo'] : '';
        $strNombre = isset($_POST['nombre_campo']) ? $_POST['nombre_campo'] : '';
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurDetalleLogCampo($conn, $intLogId, $strCampo, $strNombre);
        die();
    }

    if (isset($_POST['drawBlurValorActualCampoUAC'])) {
        $strCampo = isset($_POST['campo']) ? $_POST['campo'] : '';
        $strNombre = isset($_POST['nombre_campo']) ? $_POST['nombre_campo'] : '';
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurValorActualCampo($conn, $intSyllabusUAC, $strCampo, $strNombre);
        die();
    }

    if (isset($_POST['drawBlurBitacoraTodosEval'])) {
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraTodosEval($conn, $intSyllabusUAC);
        die();
    }

    if (isset($_POST['drawBlurBitacoraEval'])) {
        $intEvalId = isset($_POST['syllabus_uac_eval']) ? intval($_POST['syllabus_uac_eval']) : 0;
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)
            || !uac_bitacora_verificarEval($conn, $intEvalId, $intSyllabusUAC)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraEval($conn, $intEvalId, $intSyllabusUAC);
        die();
    }

    if (isset($_POST['drawBlurBitacoraEvalEliminado'])) {
        $intEvalId = isset($_POST['syllabus_uac_eval']) ? intval($_POST['syllabus_uac_eval']) : 0;
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraEvalEliminado($conn, $intEvalId);
        die();
    }

    if (isset($_POST['drawBlurBitacoraTodosBiblioEv'])) {
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraTodosBiblioEv($conn, $intSyllabusUAC);
        die();
    }

    if (isset($_POST['drawBlurBitacoraBiblioEv'])) {
        $intBiblioId = isset($_POST['syllabus_uac_biblio']) ? intval($_POST['syllabus_uac_biblio']) : 0;
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)
            || !uac_bitacora_verificarBiblio($conn, $intBiblioId, $intSyllabusUAC)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraBiblioEv($conn, $intBiblioId, $intSyllabusUAC);
        die();
    }

    if (isset($_POST['drawBlurBitacoraBiblioEvEliminado'])) {
        $intBiblioId = isset($_POST['syllabus_uac_biblio']) ? intval($_POST['syllabus_uac_biblio']) : 0;
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraBiblioEvEliminado($conn, $intBiblioId);
        die();
    }

    if (isset($_POST['drawBlurBitacoraTodosExp'])) {
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraTodosExp($conn, $intSyllabusUAC);
        die();
    }

    if (isset($_POST['drawBlurBitacoraExp'])) {
        $intExpId = isset($_POST['syllabus_uac_exp']) ? intval($_POST['syllabus_uac_exp']) : 0;
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)
            || !uac_bitacora_verificarExp($conn, $intExpId, $intSyllabusUAC)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraExp($conn, $intExpId, $intSyllabusUAC);
        die();
    }

    if (isset($_POST['drawBlurBitacoraExpEliminado'])) {
        $intExpId = isset($_POST['syllabus_uac_exp']) ? intval($_POST['syllabus_uac_exp']) : 0;
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraExpEliminado($conn, $intExpId);
        die();
    }

    if (isset($_POST['drawBlurBitacoraTodosCronogramas'])) {
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraTodosCronogramas($conn, $intSyllabusUAC);
        die();
    }

    if (isset($_POST['drawBlurBitacoraCronoAdjunto'])) {
        $intCronoId = isset($_POST['syllabus_uac_crono']) ? intval($_POST['syllabus_uac_crono']) : 0;
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)
            || !uac_bitacora_verificarCronograma($conn, $intCronoId, $intSyllabusUAC)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraCronoAdjunto($conn, $intCronoId, $intSyllabusUAC);
        die();
    }

    if (isset($_POST['drawBlurBitacoraCronoEliminado'])) {
        $intCronoId = isset($_POST['syllabus_uac_crono']) ? intval($_POST['syllabus_uac_crono']) : 0;
        if (!uac_bitacora_verificarSyllabus($conn, $intSyllabusUAC, $intCimp)) {
            echo '<p>Acceso denegado.</p>';
            die();
        }
        uac_drawBlurBitacoraCronoEliminado($conn, $intCronoId);
        die();
    }
}
