<?php
/**
 * Persistencia de borrador syllabus catedratico (desde POST/FILES).
 * Requiere: syllabus_catedratico_ws.php (syl_ws_cronogramaExtensionPermitida),
 *           syllabus_catedratico_bitacora.php (uac_* biblio),
 *           core/aws-php-sdk/s3_carga_descarga_funciones.php (core_SubirArchivoS3).
 */

require_once __DIR__ . '/syllabus_catedratico_spellcheck.php';

function syl_ws_errorCronogramaSinContenido($arrFilas = [])
{
    return [
        'ok'                  => false,
        'sin_contenido'       => true,
        'msg'                 => 'Existen adjuntos sin contenido',
        'sin_contenido_filas' => array_values(array_unique(array_map('strval', $arrFilas))),
    ];
}

function syl_ws_cronoTieneArchivoSubido($n)
{
    return (
        isset($_FILES["archivo_cronograma_{$n}"])
        && $_FILES["archivo_cronograma_{$n}"]['error'] === UPLOAD_ERR_OK
        && is_uploaded_file($_FILES["archivo_cronograma_{$n}"]['tmp_name'])
    );
}

function syl_ws_listarUploadsCronogramaPendientes()
{
    $arrUploads = [];

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidNewCrono_') !== 0 || $value !== '1') {
            continue;
        }
        $n = substr($key, strlen('hidNewCrono_'));
        if (!syl_ws_cronoTieneArchivoSubido($n)) {
            continue;
        }
        $arrUploads[] = [
            'fila'     => $n,
            'tmp'      => $_FILES["archivo_cronograma_{$n}"]['tmp_name'],
            'nombre'   => $_FILES["archivo_cronograma_{$n}"]['name'],
            'crono_id' => 0,
        ];
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidUpdateCrono_') !== 0) {
            continue;
        }
        $n = substr($key, strlen('hidUpdateCrono_'));
        $strDelete = isset($_POST["hidDeleteCrono_{$n}"]) ? $_POST["hidDeleteCrono_{$n}"] : 'N';
        $strNew    = isset($_POST["hidNewCrono_{$n}"])    ? $_POST["hidNewCrono_{$n}"]    : 'N';
        $strEdited = isset($_POST["hidEditedCrono_{$n}"]) ? $_POST["hidEditedCrono_{$n}"] : 'N';

        if ($strDelete === 'Y' || $strNew === '1' || $strEdited !== 'Y') {
            continue;
        }
        if (!syl_ws_cronoTieneArchivoSubido($n)) {
            continue;
        }

        $arrUploads[] = [
            'fila'     => $n,
            'tmp'      => $_FILES["archivo_cronograma_{$n}"]['tmp_name'],
            'nombre'   => $_FILES["archivo_cronograma_{$n}"]['name'],
            'crono_id' => intval($value),
        ];
    }

    return $arrUploads;
}

function syl_ws_validarContenidoUploadsCronograma($arrUploads)
{
    $arrFilas = [];

    foreach ($arrUploads as $arrUp) {
        $strExt = strtolower(pathinfo($arrUp['nombre'], PATHINFO_EXTENSION));
        if (!syl_ws_cronogramaExtensionPermitida($strExt)) {
            continue;
        }

        $arrVal = syl_spell_verificarContenidoLocal(
            $arrUp['tmp'],
            $arrUp['nombre'],
            $arrUp['crono_id']
        );
        if (!empty($arrVal['sin_contenido'])) {
            $arrFilas[] = $arrUp['fila'];
        }
    }

    return $arrFilas;
}


function syl_ws_guardarSyllabusUACDesdePost($globalConnection, $intCimp, $intAddUser, $bolCommit = true)
{
    $intCimp    = intval($intCimp);
    $intAddUser = intval($intAddUser);

    if ($intCimp <= 0 || $intAddUser <= 0) {
        return ['ok' => false, 'msg' => 'Parametros invalidos'];
    }

    $strEditedNormas             = isset($_POST['hidEditedNormas']) ? $_POST['hidEditedNormas'] : 'N';
    $strEditedUsoIA              = isset($_POST['hidEditedUsoIA']) ? $_POST['hidEditedUsoIA'] : 'N';
    $strEditedPensamientoCritico = isset($_POST['hidEditedPensamientoCritico']) ? $_POST['hidEditedPensamientoCritico'] : 'N';

    $stidCheck = oci_parse($globalConnection,
        "SELECT SYLLABUS_UA_CATEDRATICO
         FROM   SYLLABUS_UA_CATEDRATICO
         WHERE  CURSO_IMPARTIDO = :p_curso_impartido
           AND  FECHA_INICIO IS NULL
           AND  FECHA_FIN IS NULL");
    oci_bind_by_name($stidCheck, ':p_curso_impartido', $intCimp, -1, SQLT_INT);
    oci_execute($stidCheck);
    $rowCheck = oci_fetch_array($stidCheck, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stidCheck);

    $intId = 0;

    if ($rowCheck) {
        $intId = intval($rowCheck['SYLLABUS_UA_CATEDRATICO']);

        if ($strEditedNormas === 'Y' && isset($_POST['normas'])) {
            $strNormas = utf8_decode($_POST['normas']);

            $stidUpd = oci_parse($globalConnection,
                "UPDATE SYLLABUS_UA_CATEDRATICO
                 SET    NORMAS_REGLAS = EMPTY_CLOB(),
                        MOD_USER      = :p_mod_user,
                        MOD_FECHA     = SYSDATE
                 WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id
                 RETURNING NORMAS_REGLAS INTO :lob_normas");
            $lobNormas = oci_new_descriptor($globalConnection, OCI_D_LOB);
            oci_bind_by_name($stidUpd, ':p_mod_user',   $intAddUser, -1, SQLT_INT);
            oci_bind_by_name($stidUpd, ':p_syllabus_uac_id',         $intId,      -1, SQLT_INT);
            oci_bind_by_name($stidUpd, ':lob_normas', $lobNormas,  -1, OCI_B_CLOB);
            oci_execute($stidUpd, OCI_NO_AUTO_COMMIT);
            $lobNormas->write($strNormas);
            $lobNormas->free();
            oci_free_statement($stidUpd);
        }

    } else {
        $stidIns = oci_parse($globalConnection,
            "INSERT INTO SYLLABUS_UA_CATEDRATICO
                (SYLLABUS_UA_CATEDRATICO, CURSO_IMPARTIDO, SYLLABUS_UA,
                 NORMAS_REGLAS, USO_IA, PENSAMIENTO_CRITICO,
                 ADD_USER, ADD_FECHA)
             VALUES
                (SEQ_SYLLABUS_UAC.NEXTVAL, :p_curso_impartido, NULL,
                 EMPTY_CLOB(), EMPTY_CLOB(), EMPTY_CLOB(),
                 :p_add_user, SYSDATE)
             RETURNING SYLLABUS_UA_CATEDRATICO, NORMAS_REGLAS
             INTO :p_nuevo_id, :lob_normas");

        $lobNormas = oci_new_descriptor($globalConnection, OCI_D_LOB);
        $intNewId  = 0;

        oci_bind_by_name($stidIns, ':p_curso_impartido',       $intCimp,    -1, SQLT_INT);
        oci_bind_by_name($stidIns, ':p_add_user',   $intAddUser, -1, SQLT_INT);
        oci_bind_by_name($stidIns, ':p_nuevo_id',     $intNewId,   -1, SQLT_INT);
        oci_bind_by_name($stidIns, ':lob_normas', $lobNormas,  -1, OCI_B_CLOB);

        oci_execute($stidIns, OCI_NO_AUTO_COMMIT);

        if ($strEditedNormas === 'Y' && isset($_POST['normas'])) {
            $strNormas = utf8_decode($_POST['normas']);
            $lobNormas->write($strNormas);
        }

        $lobNormas->free();
        oci_free_statement($stidIns);

        $intId = intval($intNewId);
    }

    if ($intId <= 0) {
        oci_rollback($globalConnection);
        return ['ok' => false, 'msg' => 'No se pudo obtener o crear borrador'];
    }

    if ($strEditedUsoIA === 'Y' && isset($_POST['uso_ia'])) {
        $strUsoIA = utf8_decode($_POST['uso_ia']);

        $stidUpdUsoIA = oci_parse($globalConnection,
            "UPDATE SYLLABUS_UA_CATEDRATICO
             SET    USO_IA = EMPTY_CLOB(),
                    MOD_USER = :p_mod_user,
                    MOD_FECHA = SYSDATE
             WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id
             RETURNING USO_IA INTO :lob_uso_ia");
        $lobUsoIA = oci_new_descriptor($globalConnection, OCI_D_LOB);
        oci_bind_by_name($stidUpdUsoIA, ':p_mod_user',   $intAddUser, -1, SQLT_INT);
        oci_bind_by_name($stidUpdUsoIA, ':p_syllabus_uac_id',         $intId,      -1, SQLT_INT);
        oci_bind_by_name($stidUpdUsoIA, ':lob_uso_ia', $lobUsoIA,   -1, OCI_B_CLOB);
        oci_execute($stidUpdUsoIA, OCI_NO_AUTO_COMMIT);
        $lobUsoIA->write($strUsoIA);
        $lobUsoIA->free();
        oci_free_statement($stidUpdUsoIA);
    }

    if ($strEditedPensamientoCritico === 'Y' && isset($_POST['pensamiento_critico'])) {
        $strPensamientoCritico = utf8_decode($_POST['pensamiento_critico']);

        $stidUpdPC = oci_parse($globalConnection,
            "UPDATE SYLLABUS_UA_CATEDRATICO
             SET    PENSAMIENTO_CRITICO = EMPTY_CLOB(),
                    MOD_USER = :p_mod_user,
                    MOD_FECHA = SYSDATE
             WHERE  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id
             RETURNING PENSAMIENTO_CRITICO INTO :lob_pensamiento_critico");
        $lobPensamientoCritico = oci_new_descriptor($globalConnection, OCI_D_LOB);
        oci_bind_by_name($stidUpdPC, ':p_mod_user', $intAddUser, -1, SQLT_INT);
        oci_bind_by_name($stidUpdPC, ':p_syllabus_uac_id', $intId, -1, SQLT_INT);
        oci_bind_by_name($stidUpdPC, ':lob_pensamiento_critico', $lobPensamientoCritico, -1, OCI_B_CLOB);
        oci_execute($stidUpdPC, OCI_NO_AUTO_COMMIT);
        $lobPensamientoCritico->write($strPensamientoCritico);
        $lobPensamientoCritico->free();
        oci_free_statement($stidUpdPC);
    }

    // -------------------------------------------------------
    // EVALUACION: DELETE -> INSERT -> UPDATE (por fila)
    // -------------------------------------------------------

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidDeleteEval_') !== 0 || $value !== 'Y') {
            continue;
        }
        $n = substr($key, strlen('hidDeleteEval_'));
        $intEvalId = isset($_POST["hidUpdateEval_{$n}"])
            ? intval($_POST["hidUpdateEval_{$n}"]) : 0;

        if ($intEvalId <= 0) {
            continue;
        }

        $stid = oci_parse($globalConnection,
            "DELETE FROM SYLLABUS_UA_CATEDRATICO_EVALUACION
             WHERE SYLLABUS_UAC_EVALUACION = :p_evaluacion_id
               AND SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
        oci_bind_by_name($stid, ':p_evaluacion_id', $intEvalId, -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_syllabus_uac_id', $intId,     -1, SQLT_INT);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stid);
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidNewEval_') !== 0 || $value !== '1') {
            continue;
        }
        $n = substr($key, strlen('hidNewEval_'));
        $strRubro = isset($_POST["txtRubroEval_{$n}"])
            ? trim($_POST["txtRubroEval_{$n}"]) : '';
        $numPct   = isset($_POST["txtPctEval_{$n}"])
            ? floatval($_POST["txtPctEval_{$n}"]) : 0;

        if ($strRubro === '') {
            continue;
        }

        $strRubro = utf8_decode(substr($strRubro, 0, 500));

        $stid = oci_parse($globalConnection,
            "INSERT INTO SYLLABUS_UA_CATEDRATICO_EVALUACION
                (SYLLABUS_UAC_EVALUACION, SYLLABUS_UA_CATEDRATICO,
                 RUBRO, PORCENTAJE, ADD_USER, ADD_FECHA)
             VALUES
                (SEQ_SYLLABUS_UAC_EVAL.NEXTVAL, :p_syllabus_uac_id,
                 :p_rubro, :p_porcentaje, :p_add_user, SYSDATE)");
        oci_bind_by_name($stid, ':p_syllabus_uac_id',   $intId,      -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_rubro', $strRubro,   500);
        oci_bind_by_name($stid, ':p_porcentaje',   $numPct,     -1);
        oci_bind_by_name($stid, ':p_add_user', $intAddUser, -1, SQLT_INT);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stid);
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidUpdateEval_') !== 0) {
            continue;
        }
        $n = substr($key, strlen('hidUpdateEval_'));
        $intEvalId = intval($value);

        $strDelete = isset($_POST["hidDeleteEval_{$n}"]) ? $_POST["hidDeleteEval_{$n}"] : 'N';
        $strEdited = isset($_POST["hidEditedEval_{$n}"]) ? $_POST["hidEditedEval_{$n}"] : 'N';

        if ($strDelete === 'Y' || $strEdited !== 'Y' || $intEvalId <= 0) {
            continue;
        }

        $strRubro = isset($_POST["txtRubroEval_{$n}"])
            ? trim($_POST["txtRubroEval_{$n}"]) : '';
        $numPct   = isset($_POST["txtPctEval_{$n}"])
            ? floatval($_POST["txtPctEval_{$n}"]) : 0;

        if ($strRubro === '') {
            continue;
        }

        $strRubro = utf8_decode(substr($strRubro, 0, 500));

        $stid = oci_parse($globalConnection,
            "UPDATE SYLLABUS_UA_CATEDRATICO_EVALUACION
             SET    RUBRO       = :p_rubro,
                    PORCENTAJE  = :p_porcentaje,
                    MOD_USER    = :p_add_user,
                    MOD_FECHA   = SYSDATE
             WHERE  SYLLABUS_UAC_EVALUACION = :p_evaluacion_id
               AND  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
        oci_bind_by_name($stid, ':p_rubro', $strRubro,   500);
        oci_bind_by_name($stid, ':p_porcentaje',   $numPct,     -1);
        oci_bind_by_name($stid, ':p_add_user', $intAddUser, -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_evaluacion_id',   $intEvalId,  -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_syllabus_uac_id',   $intId,      -1, SQLT_INT);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stid);
    }

    // -------------------------------------------------------
    // BIBLIOGRAFIA: DELETE -> INSERT -> UPDATE (por fila)
    // -------------------------------------------------------

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidDeleteBiblio_') !== 0 || $value !== 'Y') {
            continue;
        }
        $n = substr($key, strlen('hidDeleteBiblio_'));
        $intBiblioId = isset($_POST["hidUpdateBiblio_{$n}"])
            ? intval($_POST["hidUpdateBiblio_{$n}"]) : 0;

        if ($intBiblioId <= 0) {
            continue;
        }

        $stid = oci_parse($globalConnection,
            "DELETE FROM SYLLABUS_UA_CATEDRATICO_BIBLIOGRAFIA
             WHERE SYLLABUS_UAC_BIBLIOGRAFIA = :p_biblio_id
               AND SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
        oci_bind_by_name($stid, ':p_biblio_id', $intBiblioId, -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_syllabus_uac_id', $intId,       -1, SQLT_INT);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stid);
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidNewBiblio_') !== 0 || $value !== '1') {
            continue;
        }
        $n = substr($key, strlen('hidNewBiblio_'));
        $strRef = isset($_POST["txtBiblio_{$n}"])
            ? $_POST["txtBiblio_{$n}"] : '';

        uac_insertReferenciaBiblioEv($globalConnection, $intId, $strRef, $intAddUser);
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidUpdateBiblio_') !== 0) {
            continue;
        }
        $n = substr($key, strlen('hidUpdateBiblio_'));
        $intBiblioId = intval($value);

        $strDelete = isset($_POST["hidDeleteBiblio_{$n}"]) ? $_POST["hidDeleteBiblio_{$n}"] : 'N';
        $strEdited = isset($_POST["hidEditedBiblio_{$n}"]) ? $_POST["hidEditedBiblio_{$n}"] : 'N';

        if ($strDelete === 'Y' || $strEdited !== 'Y' || $intBiblioId <= 0) {
            continue;
        }

        $strRef = isset($_POST["txtBiblio_{$n}"])
            ? $_POST["txtBiblio_{$n}"] : '';

        uac_guardarReferenciaBiblioEv($globalConnection, $intBiblioId, $intId, $strRef, $intAddUser);
    }

    // -------------------------------------------------------
    // EXPERIENCIAS: DELETE -> INSERT -> UPDATE (por fila)
    // -------------------------------------------------------

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidDeleteExp_') !== 0 || $value !== 'Y') {
            continue;
        }
        $n = substr($key, strlen('hidDeleteExp_'));
        $intExpId = isset($_POST["hidUpdateExp_{$n}"])
            ? intval($_POST["hidUpdateExp_{$n}"]) : 0;

        if ($intExpId <= 0) {
            continue;
        }

        $stid = oci_parse($globalConnection,
            "DELETE FROM SYLLABUS_UA_CATEDRATICO_EXPERIENCIA
             WHERE SYLLABUS_UAC_EXPERIENCIA = :p_experiencia_id
               AND SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
        oci_bind_by_name($stid, ':p_experiencia_id', $intExpId, -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_syllabus_uac_id', $intId,     -1, SQLT_INT);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stid);
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidNewExp_') !== 0 || $value !== '1') {
            continue;
        }
        $n = substr($key, strlen('hidNewExp_'));
        $strDesc = isset($_POST["txtExp_{$n}"])
            ? trim($_POST["txtExp_{$n}"]) : '';

        if ($strDesc === '') {
            continue;
        }

        $strDesc = utf8_decode(substr($strDesc, 0, 4000));

        $stid = oci_parse($globalConnection,
            "INSERT INTO SYLLABUS_UA_CATEDRATICO_EXPERIENCIA
                (SYLLABUS_UAC_EXPERIENCIA, SYLLABUS_UA_CATEDRATICO,
                 DESCRIPCION, ADD_USER, ADD_FECHA)
             VALUES
                (SEQ_SYLLABUS_UAC_EXP.NEXTVAL, :p_syllabus_uac_id,
                 :p_descripcion, :p_add_user, SYSDATE)");
        oci_bind_by_name($stid, ':p_syllabus_uac_id',        $intId,      -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_descripcion', $strDesc,    4000);
        oci_bind_by_name($stid, ':p_add_user',       $intAddUser, -1, SQLT_INT);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stid);
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidUpdateExp_') !== 0) {
            continue;
        }
        $n = substr($key, strlen('hidUpdateExp_'));
        $intExpId = intval($value);

        $strDelete = isset($_POST["hidDeleteExp_{$n}"]) ? $_POST["hidDeleteExp_{$n}"] : 'N';
        $strEdited = isset($_POST["hidEditedExp_{$n}"]) ? $_POST["hidEditedExp_{$n}"] : 'N';

        if ($strDelete === 'Y' || $strEdited !== 'Y' || $intExpId <= 0) {
            continue;
        }

        $strDesc = isset($_POST["txtExp_{$n}"])
            ? trim($_POST["txtExp_{$n}"]) : '';

        if ($strDesc === '') {
            continue;
        }

        $strDesc = utf8_decode(substr($strDesc, 0, 4000));

        $stid = oci_parse($globalConnection,
            "UPDATE SYLLABUS_UA_CATEDRATICO_EXPERIENCIA
             SET    DESCRIPCION = :p_descripcion,
                    MOD_USER    = :p_add_user,
                    MOD_FECHA   = SYSDATE
             WHERE  SYLLABUS_UAC_EXPERIENCIA = :p_experiencia_id
               AND  SYLLABUS_UA_CATEDRATICO = :p_syllabus_uac_id");
        oci_bind_by_name($stid, ':p_descripcion', $strDesc,      4000);
        oci_bind_by_name($stid, ':p_add_user',       $intAddUser, -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_experiencia_id',          $intExpId,  -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_syllabus_uac_id',          $intId,      -1, SQLT_INT);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stid);
    }

    // -------------------------------------------------------
    // CRONOGRAMA: CRUD por fila (tabla hija + S3)
    // -------------------------------------------------------

    // Mapeo indice de fila cliente (n) -> nueva PK SYLLABUS_UAC_CRONOGRAMA,
    // para que el cliente pueda marcar/identificar los cronogramas recien creados.
    $arrCronoMap = [];
    $arrCronoRevision = [];

    $arrUploadsCrono = syl_ws_listarUploadsCronogramaPendientes();
    foreach ($arrUploadsCrono as $arrUpCrono) {
        $strExtUp = strtolower(pathinfo($arrUpCrono['nombre'], PATHINFO_EXTENSION));
        if (!syl_ws_cronogramaExtensionPermitida($strExtUp)) {
            oci_rollback($globalConnection);
            return ['ok' => false, 'msg' => 'Formato no permitido en cronograma. Use PDF, Word, Excel o PowerPoint.'];
        }
    }
    $arrFilasSinContenido = syl_ws_validarContenidoUploadsCronograma($arrUploadsCrono);
    if (count($arrFilasSinContenido) > 0) {
        oci_rollback($globalConnection);
        return syl_ws_errorCronogramaSinContenido($arrFilasSinContenido);
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidDeleteCrono_') !== 0 || $value !== 'Y') {
            continue;
        }
        $n = substr($key, strlen('hidDeleteCrono_'));
        $intCronoId = isset($_POST["hidUpdateCrono_{$n}"])
            ? intval($_POST["hidUpdateCrono_{$n}"]) : 0;
        if ($intCronoId <= 0) {
            continue;
        }
        $stid = oci_parse($globalConnection,
            "DELETE FROM SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
             WHERE SYLLABUS_UAC_CRONOGRAMA  = :p_crono_id
               AND SYLLABUS_UA_CATEDRATICO  = :p_syllabus_uac_id");
        oci_bind_by_name($stid, ':p_crono_id', $intCronoId, -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_syllabus_uac_id',  $intId,      -1, SQLT_INT);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stid);
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidNewCrono_') !== 0 || $value !== '1') {
            continue;
        }
        $n = substr($key, strlen('hidNewCrono_'));

        $bolHayArchivo = (
            isset($_FILES["archivo_cronograma_{$n}"])
            && $_FILES["archivo_cronograma_{$n}"]['error'] === UPLOAD_ERR_OK
            && is_uploaded_file($_FILES["archivo_cronograma_{$n}"]['tmp_name'])
        );

        if (!$bolHayArchivo) {
            continue;
        }

        $strNombreOrig = $_FILES["archivo_cronograma_{$n}"]['name'];
        $strExt        = strtolower(pathinfo($strNombreOrig, PATHINFO_EXTENSION));

        if (!syl_ws_cronogramaExtensionPermitida($strExt)) {
            oci_rollback($globalConnection);
            return ['ok' => false, 'msg' => 'Formato no permitido en cronograma. Use PDF, Word, Excel o PowerPoint.'];
        }

        $stidSeq = oci_parse($globalConnection,
            "SELECT SEQ_SYLLABUS_UAC_CRONO.NEXTVAL AS NID FROM DUAL");
        oci_execute($stidSeq);
        $rowSeq = oci_fetch_array($stidSeq, OCI_ASSOC + OCI_RETURN_NULLS);
        oci_free_statement($stidSeq);
        $intNuevoCronoId = intval($rowSeq['NID'] ?? 0);

        if ($intNuevoCronoId <= 0) {
            oci_rollback($globalConnection);
            return ['ok' => false, 'msg' => 'No se pudo obtener ID de cronograma'];
        }

        $strNombreS3   = 'documento_' . time() . '_' . $strNombreOrig;
        $strDirectorio = $intCimp . '/cronograma';

        $strUrlCrono = core_SubirArchivoS3(
            'syllabus-compras',
            $strNombreS3,
            $_FILES["archivo_cronograma_{$n}"]['tmp_name'],
            $strDirectorio
        );

        if ($strUrlCrono === false) {
            oci_rollback($globalConnection);
            return ['ok' => false, 'msg' => 'Error al subir el cronograma a S3'];
        }

        $strNombreOrig = utf8_decode(substr($strNombreOrig, 0, 500));
        $strUrlCrono   = substr($strUrlCrono, 0, 1000);

        $stid = oci_parse($globalConnection,
            "INSERT INTO SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
                (SYLLABUS_UAC_CRONOGRAMA, SYLLABUS_UA_CATEDRATICO,
                 PATH_ARCHIVO, NOMBRE_ARCHIVO, ADD_USER, ADD_FECHA)
             VALUES
                (:p_crono_id, :p_syllabus_uac_id, :p_path_archivo, :p_nombre_archivo, :p_add_user, SYSDATE)");
        oci_bind_by_name($stid, ':p_crono_id',       $intNuevoCronoId, -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_syllabus_uac_id',        $intId,           -1, SQLT_INT);
        oci_bind_by_name($stid, ':p_path_archivo',   $strUrlCrono,     1000);
        oci_bind_by_name($stid, ':p_nombre_archivo', $strNombreOrig,   500);
        oci_bind_by_name($stid, ':p_add_user',       $intAddUser,      -1, SQLT_INT);
        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        oci_free_statement($stid);

        $arrCronoMap[$n] = $intNuevoCronoId;
        $arrCronoRevision[] = $intNuevoCronoId;
    }

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'hidUpdateCrono_') !== 0) {
            continue;
        }
        $n = substr($key, strlen('hidUpdateCrono_'));
        $intCronoId = intval($value);

        $strDelete = isset($_POST["hidDeleteCrono_{$n}"]) ? $_POST["hidDeleteCrono_{$n}"] : 'N';
        $strNew    = isset($_POST["hidNewCrono_{$n}"])    ? $_POST["hidNewCrono_{$n}"]    : 'N';
        $strEdited = isset($_POST["hidEditedCrono_{$n}"]) ? $_POST["hidEditedCrono_{$n}"] : 'N';
        $strActivo = isset($_POST["hidActivoCrono_{$n}"]) ? $_POST["hidActivoCrono_{$n}"] : 'Y';
        $strActivo = ($strActivo === 'N') ? 'N' : 'Y';

        if ($strDelete === 'Y' || $strNew === '1' || $intCronoId <= 0) {
            continue;
        }

        $bolHayArchivo = (
            isset($_FILES["archivo_cronograma_{$n}"])
            && $_FILES["archivo_cronograma_{$n}"]['error'] === UPLOAD_ERR_OK
            && is_uploaded_file($_FILES["archivo_cronograma_{$n}"]['tmp_name'])
        );

        if ($strEdited === 'Y' && $bolHayArchivo) {
            $strNombreOrig = $_FILES["archivo_cronograma_{$n}"]['name'];
            $strExt        = strtolower(pathinfo($strNombreOrig, PATHINFO_EXTENSION));

            if (!syl_ws_cronogramaExtensionPermitida($strExt)) {
                oci_rollback($globalConnection);
                return ['ok' => false, 'msg' => 'Formato no permitido en cronograma. Use PDF, Word, Excel o PowerPoint.'];
            }

            $strNombreS3   = 'documento_' . time() . '_' . $strNombreOrig;
            $strDirectorio = $intCimp . '/cronograma';

            $strUrlCrono = core_SubirArchivoS3(
                'syllabus-compras',
                $strNombreS3,
                $_FILES["archivo_cronograma_{$n}"]['tmp_name'],
                $strDirectorio
            );

            if ($strUrlCrono === false) {
                oci_rollback($globalConnection);
                return ['ok' => false, 'msg' => 'Error al subir el cronograma a S3'];
            }

            $strNombreOrig = utf8_decode(substr($strNombreOrig, 0, 500));
            $strUrlCrono   = substr($strUrlCrono, 0, 1000);

            $stid = oci_parse($globalConnection,
                "UPDATE SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
                 SET    PATH_ARCHIVO      = :p_path_archivo,
                        NOMBRE_ARCHIVO    = :p_nombre_archivo,
                        PATH_ARCHIVO_REV  = NULL,
                        ACTIVO            = :p_activo,
                        MOD_USER          = :p_mod_user,
                        MOD_FECHA         = SYSDATE
                 WHERE  SYLLABUS_UAC_CRONOGRAMA  = :p_crono_id
                   AND  SYLLABUS_UA_CATEDRATICO  = :p_syllabus_uac_id");
            oci_bind_by_name($stid, ':p_path_archivo',   $strUrlCrono,   1000);
            oci_bind_by_name($stid, ':p_nombre_archivo', $strNombreOrig, 500);
            oci_bind_by_name($stid, ':p_activo',         $strActivo,     1);
            oci_bind_by_name($stid, ':p_mod_user',       $intAddUser,    -1, SQLT_INT);
            oci_bind_by_name($stid, ':p_crono_id',       $intCronoId,    -1, SQLT_INT);
            oci_bind_by_name($stid, ':p_syllabus_uac_id',        $intId,         -1, SQLT_INT);
            oci_execute($stid, OCI_NO_AUTO_COMMIT);
            oci_free_statement($stid);

            $arrCronoRevision[] = $intCronoId;

        } else {
            $stid = oci_parse($globalConnection,
                "UPDATE SYLLABUS_UA_CATEDRATICO_CRONOGRAMA
                 SET    ACTIVO    = :p_activo,
                        MOD_USER  = :p_mod_user,
                        MOD_FECHA = SYSDATE
                 WHERE  SYLLABUS_UAC_CRONOGRAMA  = :p_crono_id
                   AND  SYLLABUS_UA_CATEDRATICO  = :p_syllabus_uac_id");
            oci_bind_by_name($stid, ':p_activo',   $strActivo,  1);
            oci_bind_by_name($stid, ':p_mod_user', $intAddUser, -1, SQLT_INT);
            oci_bind_by_name($stid, ':p_crono_id', $intCronoId, -1, SQLT_INT);
            oci_bind_by_name($stid, ':p_syllabus_uac_id',  $intId,      -1, SQLT_INT);
            oci_execute($stid, OCI_NO_AUTO_COMMIT);
            oci_free_statement($stid);
        }
    }

    if ($bolCommit) {
        oci_commit($globalConnection);
    }

    return [
        'ok'                 => true,
        'msg'                => 'Guardado correctamente',
        'id'                 => $intId,
        'cronograma_map'     => $arrCronoMap,
        'cronograma_revision'=> array_values(array_unique(array_map('intval', $arrCronoRevision))),
    ];
}
