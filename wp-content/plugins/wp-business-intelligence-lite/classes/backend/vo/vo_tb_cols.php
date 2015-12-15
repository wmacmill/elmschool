<?php

/******************************************************************************
	WP Business Intelligence Lite
	Author: WP Business Intelligence
	Website: www.wpbusinessintelligence.com
	Contact: http://www.wpbusinessintelligence.com/contactus/

	This file is part of WP Business Intelligence Lite.

    WP Business Intelligence Lite is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    WP Business Intelligence Lite is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with WP Business Intelligence Lite; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
	
	You can find a copy of the GPL licence here:
	http://www.gnu.org/licenses/gpl-3.0.html
******************************************************************************/


class vo_tb_cols{

var $id;
var $tb_id;
var $col_label;
var $is_visible;

function vo_tb_cols($id, $tb_id, $col_label, $is_visible){
$this->id = $id;
$this->tb_id = $tb_id;
$this->col_label = $col_label;
$this->is_visible = $is_visible;
}

function set_id($id){$this->id = $id;}
function set_tb_id($tb_id){$this->tb_id = $tb_id;}
function set_col_label($col_label){$this->col_label = $col_label;}
function set_is_visible($is_visible){$this->is_visible = $is_visible;}

}


?>
