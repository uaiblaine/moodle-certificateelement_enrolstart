<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for the enrolment start date element.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['dateformat'] = 'Formato da data';
$string['dateformat_help'] = 'Como a data de início da inscrição é escrita no certificado.

* Cada opção mostra a mesma data de exemplo, 6 de julho de 2018, escrita naquele formato.
* A data segue o idioma e o fuso horário em que o certificado é gerado.

**Exemplo**: "6 de julho de 2018" imprime o dia, o mês por extenso e o ano.';
$string['pluginname'] = 'Data de início da inscrição';
$string['privacy:metadata:issues'] = 'A tabela de certificados emitidos da ferramenta de certificados, em cujos dados o elemento de data de início da inscrição guarda uma cópia da data de início da inscrição que o certificado imprime.';
$string['privacy:metadata:issues:data'] = 'A data em que a inscrição do participante no curso começou, como era quando o certificado foi emitido.';
$string['privacy:metadata:issues:userid'] = 'O usuário para quem o certificado foi emitido.';
$string['startrule'] = 'Imprime a data em que a inscrição do participante no curso começou: a data de início mais antiga entre as inscrições ativas ou, quando o método de inscrição não registra data de início, a data em que a inscrição foi criada. A data é fixada quando o certificado é emitido. Certificados emitidos fora de um curso não imprimem nada.';
