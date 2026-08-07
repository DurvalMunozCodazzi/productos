<?php
if (!defined('ABSPATH')) exit;

/**
 * Lector de .xlsx sin dependencias externas (no requiere Composer/vendor).
 * Un archivo .xlsx es un .zip con XML adentro — lo leemos directo con
 * ZipArchive + SimpleXML, que vienen incluidos en PHP en cualquier hosting.
 * Alcanza para lo que necesita el importador: nombres de hoja y celdas en texto.
 */
class CLC_Xlsx_Reader {

    private $zip;
    private $shared_strings = [];
    private $hojas = []; // nombre => ruta interna del XML de la hoja

    public static function abrir($ruta) {
        $lector = new self();
        return $lector->cargar($ruta) ? $lector : null;
    }

    private function cargar($ruta) {
        $this->zip = new ZipArchive();
        if ($this->zip->open($ruta) !== true) {
            return false;
        }

        $this->cargar_shared_strings();
        $this->cargar_hojas();

        return !empty($this->hojas);
    }

    private function leer_xml($nombre_interno) {
        $contenido = $this->zip->getFromName($nombre_interno);
        if ($contenido === false) {
            return null;
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contenido);
        libxml_use_internal_errors(false);
        return $xml ?: null;
    }

    private function cargar_shared_strings() {
        $xml = $this->leer_xml('xl/sharedStrings.xml');
        if (!$xml) {
            return;
        }
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $this->shared_strings[] = (string) $si->t;
            } else {
                // texto con formato mixto (varios <r><t>...): concatenamos los fragmentos
                $texto = '';
                foreach ($si->r as $run) {
                    $texto .= (string) $run->t;
                }
                $this->shared_strings[] = $texto;
            }
        }
    }

    private function cargar_hojas() {
        $workbook = $this->leer_xml('xl/workbook.xml');
        $rels = $this->leer_xml('xl/_rels/workbook.xml.rels');
        if (!$workbook || !$rels) {
            return;
        }

        $mapa_rels = [];
        foreach ($rels->Relationship as $rel) {
            $mapa_rels[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $ns = $workbook->getNamespaces(true);
        $r_ns = $ns['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        foreach ($workbook->sheets->sheet as $sheet) {
            $nombre = (string) $sheet['name'];
            $attrs_r = $sheet->attributes($r_ns);
            $rid = (string) $attrs_r['id'];
            $target = $mapa_rels[$rid] ?? null;
            if ($target) {
                $this->hojas[$nombre] = 'xl/' . ltrim($target, '/');
            }
        }
    }

    public function nombres_de_hoja() {
        return array_keys($this->hojas);
    }

    /** Convierte "A1", "BC23" en índice de columna base 0 (A=0, B=1...). */
    private function columna_a_indice($ref) {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $letras = $m[1] ?? 'A';
        $indice = 0;
        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord($letra) - ord('A') + 1);
        }
        return $indice - 1;
    }

    /** Devuelve la hoja como array de filas (base 1), cada fila un array de celdas (base 0). */
    public function filas_de_hoja($nombre_hoja) {
        if (!isset($this->hojas[$nombre_hoja])) {
            return [];
        }
        $xml = $this->leer_xml($this->hojas[$nombre_hoja]);
        if (!$xml || !isset($xml->sheetData)) {
            return [];
        }

        $filas = [];
        foreach ($xml->sheetData->row as $row) {
            $num_fila = (int) $row['r'];
            $fila = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = $this->columna_a_indice($ref);
                $tipo = (string) $c['t'];

                if ($tipo === 's') {
                    $indice = (int) $c->v;
                    $valor = $this->shared_strings[$indice] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = (string) $c->is->t;
                } elseif (isset($c->v)) {
                    $valor = (string) $c->v;
                } else {
                    $valor = '';
                }

                $fila[$col] = $valor;
            }
            if (!empty($fila)) {
                ksort($fila);
                $filas[$num_fila] = $fila;
            }
        }
        return $filas;
    }

    public function cerrar() {
        if ($this->zip) {
            $this->zip->close();
        }
    }
}
