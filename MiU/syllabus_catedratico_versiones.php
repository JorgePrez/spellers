<?php
/**
 * Control de versiones publicadas — syllabus catedratico.
 * Borrador: FECHA_INICIO IS NULL AND FECHA_FIN IS NULL
 * Publicada vigente: FECHA_INICIO IS NOT NULL AND FECHA_FIN IS NULL
 * Al publicar: UPDATE borrador actual ? publicado; INSERT nuevo borrador con copia.
 */

function syl_uac_getIdBorrador($conn, $intCimp)
{
    $intCimp = intval($intCimp);
    if ($intCimp <= 0) {
        return 0;
    }

    $stid = oci_parse($conn,
        "SELECT SYLLABUS_UA_CATEDRATICO
         FROM   SYLLABUS_UA_CATEDRATICO
         WHERE  CURSO_IMPARTIDO = :p_curso_impartido
           AND  FECHA_INICIO IS NULL
           AND  FECHA_FIN IS NULL");
    oci_bind_by_name($stid, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    return $row ? intval($row['SYLLABUS_UA_CATEDRATICO']) : 0;
}

function syl_uac_getIdPublicadaVigente($conn, $intCimp)
{
    $intCimp = intval($intCimp);
    if ($intCimp <= 0) {
        return 0;
    }

    $stid = oci_parse($conn,
        "SELECT SYLLABUS_UA_CATEDRATICO
         FROM   SYLLABUS_UA_CATEDRATICO
         WHERE  CURSO_IMPARTIDO = :p_curso_impartido
           AND  FECHA_INICIO IS NOT NULL
           AND  FECHA_FIN IS NULL");
    oci_bind_by_name($stid, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    return $row ? intval($row['SYLLABUS_UA_CATEDRATICO']) : 0;
}

function syl_uac_getCursoDesdeCimp($conn, $intCimp)
{
    $intCimp = intval($intCimp);
    if ($intCimp <= 0) {
        return 0;
    }

    $stid = oci_parse($conn, "SELECT CURSO FROM CURSO_IMPARTIDO WHERE CURSO_IMPARTIDO = :p_curso_impartido");
    oci_bind_by_name($stid, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    return $row ? intval($row['CURSO']) : 0;
}

function syl_uac_getSyllabusUAActiva($conn, $intCurso)
{
    $intCurso = intval($intCurso);
    if ($intCurso <= 0) {
        return 0;
    }

    $stid = oci_parse($conn,
        "SELECT SYLLABUS_UA FROM SYLLABUS_UA
         WHERE CURSO = :p_curso AND FECHA_FIN IS NULL");
    oci_bind_by_name($stid, ':p_curso', $intCurso, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    return $row ? intval($row['SYLLABUS_UA']) : 0;
}

function syl_uac_contarPublicaciones($conn, $intCimp)
{
    $intCimp = intval($intCimp);
    if ($intCimp <= 0) {
        return 0;
    }

    $stid = oci_parse($conn,
        "SELECT COUNT(*) AS CNT
         FROM   SYLLABUS_UA_CATEDRATICO
         WHERE  CURSO_IMPARTIDO = :p_curso_impartido
           AND  FECHA_INICIO IS NOT NULL");
    oci_bind_by_name($stid, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    return $row ? intval($row['CNT']) : 0;
}

function syl_uac_getUltimaPublicacion($conn, $intCimp)
{
    $intCimp = intval($intCimp);
    if ($intCimp <= 0) {
        return null;
    }

    $stid = oci_parse($conn,
        "SELECT SYLLABUS_UA_CATEDRATICO,
                TO_CHAR(FECHA_INICIO, 'DD/MM/YYYY HH24:MI') AS FECHA_FMT,
                FECHA_INICIO
         FROM   SYLLABUS_UA_CATEDRATICO
         WHERE  CURSO_IMPARTIDO = :p_curso_impartido
           AND  FECHA_INICIO IS NOT NULL
           AND  FECHA_FIN IS NULL");
    oci_bind_by_name($stid, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    return $row ? $row : null;
}

function syl_uac_verificarSnapshotPublicado($conn, $intSnapshotId, $intCimp)
{
    $intSnapshotId = intval($intSnapshotId);
    $intCimp = intval($intCimp);
    if ($intSnapshotId <= 0 || $intCimp <= 0) {
        return false;
    }

    $stid = oci_parse($conn,
        "SELECT PATH_PDF_COMPLETO
         FROM   SYLLABUS_UA_CATEDRATICO
         WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id
           AND  CURSO_IMPARTIDO = :p_curso_impartido
           AND  FECHA_INICIO IS NOT NULL");
    oci_bind_by_name($stid, ':p_syllabus_uac_id', $intSnapshotId, -1, SQLT_INT);
    oci_bind_by_name($stid, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    return ($row && trim($row['PATH_PDF_COMPLETO'] ?? '') !== '') ? trim($row['PATH_PDF_COMPLETO']) : false;
}

function syl_uac_cerrarPublicacionVigente($conn, $intCimp, $intAddUser)
{
    $stid = oci_parse($conn,
        "UPDATE SYLLABUS_UA_CATEDRATICO
         SET    FECHA_FIN = SYSDATE,
                MOD_USER  = :p_mod_user,
                MOD_FECHA = SYSDATE
         WHERE  CURSO_IMPARTIDO = :p_curso_impartido
           AND  FECHA_INICIO IS NOT NULL
           AND  FECHA_FIN IS NULL");
    oci_bind_by_name($stid, ':p_mod_user', $intAddUser, -1, SQLT_INT);
    oci_bind_by_name($stid, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stid, OCI_NO_AUTO_COMMIT);
    oci_free_statement($stid);
}

function syl_uac_copiarClobARegistro($conn, $strValor, $lobDestino)
{
    if ($strValor === null || $strValor === '') {
        return;
    }
    $lobDestino->write($strValor);
}

function syl_uac_copiarHijosDesdeRegistro($conn, $intIdOrigen, $intIdDestino, $intAddUser)
{
    $intIdOrigen  = intval($intIdOrigen);
    $intIdDestino = intval($intIdDestino);
    $intAddUser   = intval($intAddUser);

    if ($intIdOrigen <= 0 || $intIdDestino <= 0) {
        return false;
    }

    $stidEval = oci_parse($conn,
        "SELECT RUBRO, PORCENTAJE
         FROM   SYLLABUS_UA_CATEDRATICO_EVALUACION
         WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
    oci_bind_by_name($stidEval, ':p_syllabus_uac_id', $intIdOrigen, -1, SQLT_INT);
    oci_execute($stidEval);
    while ($rowEval = oci_fetch_array($stidEval, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $stidInsEv = oci_parse($conn,
            "INSERT INTO SYLLABUS_UA_CATEDRATICO_EVALUACION
                (SYLLABUS_UAC_EVALUACION, SYLLABUS_UA_CATEDRATICO, RUBRO, PORCENTAJE, ADD_USER, ADD_FECHA)
             VALUES
                (SEQ_SYLLABUS_UAC_EVAL.NEXTVAL, :p_syllabus_uac_id, :p_rubro, :p_porcentaje, :p_add_user, SYSDATE)");
        $strRubro = $rowEval['RUBRO'] ?? '';
        $numPct   = floatval($rowEval['PORCENTAJE'] ?? 0);
        oci_bind_by_name($stidInsEv, ':p_syllabus_uac_id', $intIdDestino, -1, SQLT_INT);
        oci_bind_by_name($stidInsEv, ':p_rubro', $strRubro, 500);
        oci_bind_by_name($stidInsEv, ':p_porcentaje', $numPct, -1);
        oci_bind_by_name($stidInsEv, ':p_add_user', $intAddUser, -1, SQLT_INT);
        oci_execute($stidInsEv, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stidInsEv);
    }
    oci_free_statement($stidEval);

    $stidBib = oci_parse($conn,
        "SELECT SYLLABUS_UAC_BIBLIOGRAFIA
         FROM   SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA
         WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
    oci_bind_by_name($stidBib, ':p_syllabus_uac_id', $intIdOrigen, -1, SQLT_INT);
    oci_execute($stidBib);
    while ($rowBib = oci_fetch_array($stidBib, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $intBibId = intval($rowBib['SYLLABUS_UAC_BIBLIOGRAFIA']);
        $strRef   = uac_getReferenciaBiblioEv($conn, $intBibId);
        if ($strRef !== '') {
            uac_insertReferenciaBiblioEv($conn, $intIdDestino, $strRef, $intAddUser, true);
        }
    }
    oci_free_statement($stidBib);

    $stidExp = oci_parse($conn,
        "SELECT DESCRIPCION
         FROM   SYLLABUS_UA_CATEDRATICO_EXPERIENCIA
         WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
    oci_bind_by_name($stidExp, ':p_syllabus_uac_id', $intIdOrigen, -1, SQLT_INT);
    oci_execute($stidExp);
    while ($rowExp = oci_fetch_array($stidExp, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $strDesc = $rowExp['DESCRIPCION'] ?? '';
        if ($strDesc === '') {
            continue;
        }
        $stidInsExp = oci_parse($conn,
            "INSERT INTO SYLLABUS_UA_CATEDRATICO_EXPERIENCIA
                (SYLLABUS_UAC_EXPERIENCIA, SYLLABUS_UA_CATEDRATICO, DESCRIPCION, ADD_USER, ADD_FECHA)
             VALUES
                (SEQ_SYLLABUS_UAC_EXP.NEXTVAL, :p_syllabus_uac_id, :p_descripcion, :p_add_user, SYSDATE)");
        oci_bind_by_name($stidInsExp, ':p_syllabus_uac_id', $intIdDestino, -1, SQLT_INT);
        oci_bind_by_name($stidInsExp, ':p_descripcion', $strDesc, 4000);
        oci_bind_by_name($stidInsExp, ':p_add_user', $intAddUser, -1, SQLT_INT);
        oci_execute($stidInsExp, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stidInsExp);
    }
    oci_free_statement($stidExp);

    $stidCrono = oci_parse($conn,
        "SELECT PATH_ARCHIVO, PATH_ARCHIVO_REV, NOMBRE_ARCHIVO, ACTIVO
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
         WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
    oci_bind_by_name($stidCrono, ':p_syllabus_uac_id', $intIdOrigen, -1, SQLT_INT);
    oci_execute($stidCrono);
    while ($rowCrono = oci_fetch_array($stidCrono, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $strPath = substr(trim($rowCrono['PATH_ARCHIVO'] ?? ''), 0, 1000);
        $strPathRev = substr(trim($rowCrono['PATH_ARCHIVO_REV'] ?? ''), 0, 1000);
        $strNom  = substr(trim($rowCrono['NOMBRE_ARCHIVO'] ?? ''), 0, 500);
        $strAct  = (($rowCrono['ACTIVO'] ?? 'Y') === 'N') ? 'N' : 'Y';
        if ($strPath === '') {
            continue;
        }
        $stidInsCr = oci_parse($conn,
            "INSERT INTO SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
                (SYLLABUS_UAC_CRONOGRAMA, SYLLABUS_UA_CATEDRATICO,
                 PATH_ARCHIVO, PATH_ARCHIVO_REV, NOMBRE_ARCHIVO, ACTIVO, ADD_USER, ADD_FECHA)
             VALUES
                (SEQ_SYLLABUS_UAC_CRONO.NEXTVAL, :p_syllabus_uac_id,
                 :p_path_archivo, :p_path_archivo_rev, :p_nombre_archivo, :p_activo, :p_add_user, SYSDATE)");
        oci_bind_by_name($stidInsCr, ':p_syllabus_uac_id', $intIdDestino, -1, SQLT_INT);
        oci_bind_by_name($stidInsCr, ':p_path_archivo', $strPath, 1000);
        $strBindRev = ($strPathRev === '') ? null : $strPathRev;
        oci_bind_by_name($stidInsCr, ':p_path_archivo_rev', $strBindRev, 1000);
        oci_bind_by_name($stidInsCr, ':p_nombre_archivo', $strNom, 500);
        oci_bind_by_name($stidInsCr, ':p_activo', $strAct, 1);
        oci_bind_by_name($stidInsCr, ':p_add_user', $intAddUser, -1, SQLT_INT);
        oci_execute($stidInsCr, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stidInsCr);
    }
    oci_free_statement($stidCrono);

    return true;
}

function syl_uac_publicarBorrador($conn, $intIdBorrador, $intSyllabusUA, $strPathPdf, $intAddUser)
{
    $intIdBorrador = intval($intIdBorrador);
    $intAddUser    = intval($intAddUser);
    $strPathPdf    = substr(trim((string) $strPathPdf), 0, 1000);
    $intSyllabusUABind = $intSyllabusUA > 0 ? $intSyllabusUA : null;

    if ($intIdBorrador <= 0 || $strPathPdf === '' || $intSyllabusUA <= 0) {
        return false;
    }

    $stid = oci_parse($conn,
        "UPDATE SYLLABUS_UA_CATEDRATICO
         SET    FECHA_INICIO      = SYSDATE,
                PATH_PDF_COMPLETO = :p_path_pdf,
                SYLLABUS_UA       = :p_syllabus_ua,
                MOD_USER          = :p_mod_user,
                MOD_FECHA         = SYSDATE
         WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id
           AND  FECHA_INICIO IS NULL
           AND  FECHA_FIN IS NULL");
    oci_bind_by_name($stid, ':p_path_pdf', $strPathPdf, 1000);
    oci_bind_by_name($stid, ':p_syllabus_ua', $intSyllabusUABind, -1, SQLT_INT);
    oci_bind_by_name($stid, ':p_mod_user', $intAddUser, -1, SQLT_INT);
    oci_bind_by_name($stid, ':p_syllabus_uac_id', $intIdBorrador, -1, SQLT_INT);
    oci_execute($stid, OCI_NO_AUTO_COMMIT);
    $intFilas = oci_num_rows($stid);
    oci_free_statement($stid);

    return ($intFilas === 1);
}

function syl_uac_crearBorradorDesdeRegistro($conn, $intIdOrigen, $intCimp, $intAddUser)
{
    $intIdOrigen = intval($intIdOrigen);
    $intCimp     = intval($intCimp);
    $intAddUser  = intval($intAddUser);

    if ($intIdOrigen <= 0 || $intCimp <= 0) {
        return 0;
    }

    $stidCab = oci_parse($conn,
        "SELECT NORMAS_REGLAS, USO_IA, PENSAMIENTO_CRITICO
         FROM   SYLLABUS_UA_CATEDRATICO
         WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
    oci_bind_by_name($stidCab, ':p_syllabus_uac_id', $intIdOrigen, -1, SQLT_INT);
    oci_execute($stidCab);
    $rowCab = oci_fetch_array($stidCab, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
    oci_free_statement($stidCab);

    if (!$rowCab) {
        return 0;
    }

    $stidIns = oci_parse($conn,
        "INSERT INTO SYLLABUS_UA_CATEDRATICO
            (SYLLABUS_UA_CATEDRATICO, CURSO_IMPARTIDO, SYLLABUS_UA,
             NORMAS_REGLAS, USO_IA, PENSAMIENTO_CRITICO,
             PATH_PDF_COMPLETO, FECHA_INICIO, FECHA_FIN,
             ADD_USER, ADD_FECHA)
         VALUES
            (SEQ_SYLLABUS_UAC.NEXTVAL, :p_curso_impartido, NULL,
             EMPTY_CLOB(), EMPTY_CLOB(), EMPTY_CLOB(),
             NULL, NULL, NULL,
             :p_add_user, SYSDATE)
         RETURNING SYLLABUS_UA_CATEDRATICO, NORMAS_REGLAS, USO_IA, PENSAMIENTO_CRITICO
         INTO :p_nuevo_id, :lob_normas, :lob_uso_ia, :lob_pc");

    $intNewId = 0;
    $lobNormas = oci_new_descriptor($conn, OCI_D_LOB);
    $lobUsoIA  = oci_new_descriptor($conn, OCI_D_LOB);
    $lobPC     = oci_new_descriptor($conn, OCI_D_LOB);

    oci_bind_by_name($stidIns, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_bind_by_name($stidIns, ':p_add_user', $intAddUser, -1, SQLT_INT);
    oci_bind_by_name($stidIns, ':p_nuevo_id', $intNewId, -1, SQLT_INT);
    oci_bind_by_name($stidIns, ':lob_normas', $lobNormas, -1, OCI_B_CLOB);
    oci_bind_by_name($stidIns, ':lob_uso_ia', $lobUsoIA, -1, OCI_B_CLOB);
    oci_bind_by_name($stidIns, ':lob_pc', $lobPC, -1, OCI_B_CLOB);
    oci_execute($stidIns, OCI_NO_AUTO_COMMIT);

    syl_uac_copiarClobARegistro($conn, $rowCab['NORMAS_REGLAS'] ?? '', $lobNormas);
    syl_uac_copiarClobARegistro($conn, $rowCab['USO_IA'] ?? '', $lobUsoIA);
    syl_uac_copiarClobARegistro($conn, $rowCab['PENSAMIENTO_CRITICO'] ?? '', $lobPC);

    $lobNormas->free();
    $lobUsoIA->free();
    $lobPC->free();
    oci_free_statement($stidIns);

    $intNewId = intval($intNewId);
    if ($intNewId <= 0) {
        return 0;
    }

    syl_uac_copiarHijosDesdeRegistro($conn, $intIdOrigen, $intNewId, $intAddUser);

    return $intNewId;
}

/**
 * Publica el borrador actual (UPDATE) y crea un nuevo borrador con copia del contenido.
 * Retorna ['id_publicado' => int, 'id_borrador' => int] o null si falla.
 */
function syl_uac_publicarBorradorYCrearNuevo($conn, $intIdBorrador, $intCimp, $intSyllabusUA, $strPathPdf, $intAddUser)
{
    $intIdBorrador = intval($intIdBorrador);
    $intCimp       = intval($intCimp);
    $intAddUser    = intval($intAddUser);
    $strPathPdf    = substr(trim((string) $strPathPdf), 0, 1000);

    if ($intIdBorrador <= 0 || $intCimp <= 0 || $strPathPdf === '' || $intSyllabusUA <= 0) {
        return null;
    }

    if (!syl_uac_publicarBorrador($conn, $intIdBorrador, $intSyllabusUA, $strPathPdf, $intAddUser)) {
        return null;
    }

    $intNuevoBorrador = syl_uac_crearBorradorDesdeRegistro($conn, $intIdBorrador, $intCimp, $intAddUser);
    if ($intNuevoBorrador <= 0) {
        return null;
    }

    return [
        'id_publicado' => $intIdBorrador,
        'id_borrador'  => $intNuevoBorrador,
    ];
}

function syl_uac_contarCronogramasActivos($conn, $intIdBorrador)
{
    $intIdBorrador = intval($intIdBorrador);
    if ($intIdBorrador <= 0) {
        return 0;
    }

    $stid = oci_parse($conn,
        "SELECT COUNT(*) AS CNT
         FROM   SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
         WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id
           AND  ACTIVO = 'Y'
           AND  PATH_ARCHIVO IS NOT NULL");
    oci_bind_by_name($stid, ':p_syllabus_uac_id', $intIdBorrador, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    return $row ? intval($row['CNT']) : 0;
}

function syl_uac_ordenarProfesoresPrincipalPrimero(array $arrProfesores)
{
    usort($arrProfesores, function ($a, $b) {
        $aAux = (stripos($a['ROL'] ?? '', 'auxiliar') !== false) ? 1 : 0;
        $bAux = (stripos($b['ROL'] ?? '', 'auxiliar') !== false) ? 1 : 0;
        return $aAux <=> $bAux;
    });
    return $arrProfesores;
}

function syl_uac_getTituloCursoImpartido($conn, $intCimp)
{
    $intCimp = intval($intCimp);
    if ($intCimp <= 0) {
        return '';
    }

    $stid = oci_parse($conn,
        "SELECT C.NOMBRE AS NOMBRE_CURSO, CI.SECCION, AC.CICLO
         FROM   CURSO_IMPARTIDO CI
         INNER  JOIN CURSO C ON CI.CURSO = C.CURSO
         INNER  JOIN CICLO_FECHA CF ON CI.CICLO_FECHA = CF.CICLO_FECHA
         LEFT   JOIN CUENTAC.VW_NCC_AGRUPADOR_CICLOS AC ON CF.CICLO_FECHA = AC.CICLO_FECHA
         WHERE  CI.CURSO_IMPARTIDO = :p_curso_impartido");
    oci_bind_by_name($stid, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);

    if (!$row) {
        return '';
    }

    $strNombre  = trim($row['NOMBRE_CURSO'] ?? '');
    $strSeccion = trim($row['SECCION'] ?? '');
    $strCiclo   = trim($row['CICLO'] ?? '');

    $stidEtica = oci_parse($conn,
        "SELECT COUNT(*) AS ETICA FROM ASIGNACION_ETICA WHERE CURSO_IMPARTIDO_ETICA = :p_curso_impartido");
    oci_bind_by_name($stidEtica, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stidEtica);
    $rowEtica = oci_fetch_array($stidEtica, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stidEtica);

    if (intval($rowEtica['ETICA'] ?? 0) > 0) {
        $stidDE = oci_parse($conn,
            "SELECT C.NOMBRE AS NOMBRE_CURSO, CI.SECCION, AC.CICLO
             FROM   CURSO_IMPARTIDO CI
             INNER  JOIN CURSO C ON CI.CURSO = C.CURSO
             INNER  JOIN CICLO_FECHA CF ON CI.CICLO_FECHA = CF.CICLO_FECHA
             LEFT   JOIN CUENTAC.VW_NCC_AGRUPADOR_CICLOS AC ON CF.CICLO_FECHA = AC.CICLO_FECHA
             WHERE  CI.CURSO_IMPARTIDO = :p_curso_impartido");
        oci_bind_by_name($stidDE, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
        oci_execute($stidDE);
        $rowDE = oci_fetch_array($stidDE, OCI_ASSOC + OCI_RETURN_NULLS);
        oci_free_statement($stidDE);

        if ($rowDE) {
            $strNombre  = trim($rowDE['NOMBRE_CURSO'] ?? $strNombre);
            $strSeccion = trim($rowDE['SECCION'] ?? $strSeccion);
            $strCiclo   = trim($rowDE['CICLO'] ?? $strCiclo);
        }
    }

    return $strNombre . ' - ' . $strSeccion . ' - ' . $strCiclo;
}

function syl_uac_drawBlurVersionesPublicadas($conn, $intCimp)
{
    $intCimp = intval($intCimp);
    if ($intCimp <= 0) {
        echo '<p>Curso impartido invalido.</p>';
        return;
    }

    $stid = oci_parse($conn,
        "SELECT s.SYLLABUS_UA_CATEDRATICO,
                TO_CHAR(s.FECHA_INICIO, 'DD/MM/YYYY HH24:MI:SS') AS FECHA_INICIO_FMT,
                TO_CHAR(s.FECHA_FIN,    'DD/MM/YYYY HH24:MI:SS') AS FECHA_FIN_FMT,
                s.FECHA_FIN,
                s.PATH_PDF_COMPLETO
         FROM   SYLLABUS_UA_CATEDRATICO s
         WHERE  s.CURSO_IMPARTIDO = :p_curso_impartido
           AND  s.FECHA_INICIO IS NOT NULL
         ORDER  BY s.FECHA_INICIO DESC");
    oci_bind_by_name($stid, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stid);

    $strTituloCurso = htmlspecialchars(syl_uac_getTituloCursoImpartido($conn, $intCimp));

    ?>
    <p class="bitacora-subtitle" style="margin-bottom:12px;">
        Historial de programas publicados &mdash; <?php print $strTituloCurso; ?>
    </p>
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha publicaci&oacute;n</th>
                <th>Fecha fin</th>
                <th>Estado</th>
                <th style="width:100px; text-align:center;">PDF</th>
            </tr>
        </thead>
        <tbody>
    <?php

    $boolHay = false;
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $boolHay = true;
        $intVerId     = intval($row['SYLLABUS_UA_CATEDRATICO']);
        $strEstado      = empty($row['FECHA_FIN']) ? 'Actual' : 'Anterior';
        $strEstadoClass = empty($row['FECHA_FIN']) ? 'pub-estado-actual' : '';
        $strFin         = empty($row['FECHA_FIN']) ? 'Vigente' : htmlspecialchars($row['FECHA_FIN_FMT']);
        $boolTienePdf = trim($row['PATH_PDF_COMPLETO'] ?? '') !== '';
        ?>
            <tr>
                <td><?php print htmlspecialchars($row['FECHA_INICIO_FMT']); ?></td>
                <td><?php print $strFin; ?></td>
                <td class="<?php print $strEstadoClass; ?>"><?php print $strEstado; ?></td>
                <td style="text-align:center;">
                <?php if ($boolTienePdf) { ?>
                    <button type="button" class="btn-toolbar-ver-pdf"
                        onclick="fntAbrirVisorPdfPublicado(<?php print $intVerId; ?>, 'Programa publicado');"
                        title="Ver PDF">
                        <span class="btn-ver-icon" aria-hidden="true">&#128065;</span> Ver
                    </button>
                <?php } else { ?>
                    <em>Sin PDF</em>
                <?php } ?>
                </td>
            </tr>
        <?php
    }
    oci_free_statement($stid);

    if (!$boolHay) {
        echo '<tr><td colspan="4"><em>No hay versiones publicadas.</em></td></tr>';
    }

    echo '</tbody></table>';
}
