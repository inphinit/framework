<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

return (
    INPHINIT_PATH !== '/' &&
    strpos(INPHINIT_PATH, '.') !== 0 &&
    strpos(INPHINIT_PATH, '/.') === false &&
    is_file($_SERVER['DOCUMENT_ROOT'] . INPHINIT_PATH)
);
