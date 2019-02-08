<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Template\Node;

use Twig_Node;
use Twig_Compiler;

class SetFrontVars extends Twig_Node {
	public function __construct($options, $lineno, $tag = null) {
		parent::__construct([], [ 'options' => $options ], $lineno, $tag);
	}
	public function compile(Twig_Compiler $compiler) {

		// Inicia la escritura del compilador
		$compiler
			->addDebugInfo($this)
			->write('Fw\Conf::setFrontVars(');

		// Escribe el array asociativo en el compilador
		$this->getAttribute('options')->compile($compiler);

		// Termina la escritura del compilador
		$compiler->write(');')
			->raw(PHP_EOL);
	}
}
?>
