<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\Parsing\ParserMode\Media;

/**
 * Mdlink : médias à la DokuWiki {{:espace:image.png?400|texte}}
 *
 * Le mode Markdown de DokuWiki n'analyse pas la syntaxe {{ }}, que le gestionnaire de médias insère
 * pourtant dans la page : ce composant la rétablit, avec les mêmes options (taille, alignement,
 * nolink, recache...) que le mode DokuWiki.
 */
class syntax_plugin_mdlink_media extends SyntaxPlugin
{
    public function getType()
    {
        return 'substition';
    }

    public function getPType()
    {
        return 'normal';
    }

    public function getSort()
    {
        return 299;
    }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('\{\{(?:[^\}\n]|(?:\}[^\}]))++\}\}', $mode, 'plugin_mdlink_media');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $p = Media::parseMedia($match);
        return [$p['type'], [$p['src'], $p['title'], $p['align'], $p['width'], $p['height'], $p['cache'], $p['linking']]];
    }

    public function render($format, Doku_Renderer $renderer, $data)
    {
        if (!$data) return false;
        [$call, $args] = $data;
        if (!method_exists($renderer, $call)) return false;
        $renderer->$call(...$args);
        return true;
    }
}
