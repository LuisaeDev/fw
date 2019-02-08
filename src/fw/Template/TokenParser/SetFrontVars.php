<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Template\TokenParser;

use Fw\Template\Node\SetFrontVars as NodeSetFrontVars;
use Twig_TokenParser;
use Twig_Token;

class SetFrontVars extends Twig_TokenParser {
	public function parse(Twig_Token $token) {
		$stream = $this->parser->getStream();

		// Opciones para el front
		$options = $this->parser->getExpressionParser()->parseHashExpression();

		$stream->expect(Twig_Token::BLOCK_END_TYPE);
		return new NodeSetFrontVars($options, $token->getLine(), $this->getTag());
	}
	public function getTag() {
		return 'frontVars';
	}
}
?>
