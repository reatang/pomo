<?php

/**
 * POMO Unit Tests
 * MO Test.
 */

namespace POMO\Tests;

use POMO\MO;
use POMO\Translations\EntryTranslations;
use POMO\Translations\Translations;

class MOTest extends POMOTestCase
{
    public function test_mo_simple()
    {
        $mo = new MO();
        $mo->import_from_file(__DIR__ . '/data/simple.mo');
        $this->assertEquals(2, count($mo->entries));
        $this->assertEquals(array('dyado'), $mo->entries['baba']->translations);
        $this->assertEquals(array('yes'), $mo->entries["kuku\nruku"]->translations);
    }

    public function test_mo_plural()
    {
        $mo = new MO();
        $mo->import_from_file(__DIR__ . '/data/plural.mo');
        $this->assertEquals(1, count($mo->entries));
        $this->assertEquals(array('oney dragoney', 'twoey dragoney', 'manyey dragoney', 'manyeyey dragoney', 'manyeyeyey dragoney'), $mo->entries['one dragon']->translations);

        $this->assertEquals('oney dragoney', $mo->translate_plural('one dragon', '%d dragons', 1));
        $this->assertEquals('twoey dragoney', $mo->translate_plural('one dragon', '%d dragons', 2));
        $this->assertEquals('twoey dragoney', $mo->translate_plural('one dragon', '%d dragons', -8));

        $mo->set_header('Plural-Forms', 'nplurals=5; plural=0');
        $this->assertEquals('oney dragoney', $mo->translate_plural('one dragon', '%d dragons', 1));
        $this->assertEquals('oney dragoney', $mo->translate_plural('one dragon', '%d dragons', 2));
        $this->assertEquals('oney dragoney', $mo->translate_plural('one dragon', '%d dragons', -8));

        $mo->set_header('Plural-Forms', 'nplurals=5; plural=n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2;');
        $this->assertEquals('oney dragoney', $mo->translate_plural('one dragon', '%d dragons', 1));
        $this->assertEquals('manyey dragoney', $mo->translate_plural('one dragon', '%d dragons', 11));
        $this->assertEquals('twoey dragoney', $mo->translate_plural('one dragon', '%d dragons', 3));

        $mo->set_header('Plural-Forms', 'nplurals=2; plural=n !=1;');
        $this->assertEquals('oney dragoney', $mo->translate_plural('one dragon', '%d dragons', 1));
        $this->assertEquals('twoey dragoney', $mo->translate_plural('one dragon', '%d dragons', 2));
        $this->assertEquals('twoey dragoney', $mo->translate_plural('one dragon', '%d dragons', -8));
    }

    public function test_mo_context()
    {
        $mo = new MO();
        $mo->import_from_file(__DIR__ . '/data/context.mo');
        $this->assertEquals(2, count($mo->entries));
        $plural_entry = new EntryTranslations(array('singular' => 'one dragon', 'plural' => '%d dragons', 'translations' => array('oney dragoney', 'twoey dragoney', 'manyey dragoney'), 'context' => 'dragonland'));
        $this->assertEquals($plural_entry, $mo->entries[$plural_entry->key()]);
        $this->assertEquals('dragonland', $mo->entries[$plural_entry->key()]->context);

        $single_entry = new EntryTranslations(array('singular' => 'one dragon', 'translations' => array('oney dragoney'), 'context' => 'not so dragon'));
        $this->assertEquals($single_entry, $mo->entries[$single_entry->key()]);
        $this->assertEquals('not so dragon', $mo->entries[$single_entry->key()]->context);
    }

    public function test_translations_merge()
    {
        $host = new Translations();
        $host->add_entry(new EntryTranslations(array('singular' => 'pink')));
        $host->add_entry(new EntryTranslations(array('singular' => 'green')));
        $guest = new Translations();
        $guest->add_entry(new EntryTranslations(array('singular' => 'green')));
        $guest->add_entry(new EntryTranslations(array('singular' => 'red')));
        $host->merge_with($guest);
        $this->assertEquals(3, count($host->entries));
        $this->assertEquals(array(), array_diff(array('pink', 'green', 'red'), array_keys($host->entries)));
    }

    public function test_export_mo_file()
    {
        $entries = array();
        $entries[] = new EntryTranslations(array('singular' => 'pink',
            'translations' => array('розов'), ));
        $no_translation_entry = new EntryTranslations(array('singular' => 'grey'));
        $entries[] = new EntryTranslations(array('singular' => 'green', 'plural' => 'greens',
            'translations' => array('зелен', 'зелени'), ));
        $entries[] = new EntryTranslations(array('singular' => 'red', 'context' => 'color',
            'translations' => array('червен'), ));
        $entries[] = new EntryTranslations(array('singular' => 'red', 'context' => 'bull',
            'translations' => array('бик'), ));
        $entries[] = new EntryTranslations(array('singular' => 'maroon', 'plural' => 'maroons', 'context' => 'context',
            'translations' => array('пурпурен', 'пурпурни'), ));

        $mo = new MO();
        $mo->set_header('Project-Id-Version', 'Baba Project 1.0');
        foreach ($entries as $entry) {
            $mo->add_entry($entry);
        }
        $mo->add_entry($no_translation_entry);

        $temp_fn = $this->temp_filename();
        $mo->export_to_file($temp_fn);

        $again = new MO();
        $again->import_from_file($temp_fn);

        $this->assertEquals(count($entries), count($again->entries));
        foreach ($entries as $entry) {
            $this->assertEquals($entry, $again->entries[$entry->key()]);
        }
    }

    public function test_export_should_not_include_empty_translations()
    {
        $mo = new MO();
        $mo->add_entry(array('singular' => 'baba', 'translations' => array('', '')));

        $temp_fn = $this->temp_filename();
        $mo->export_to_file($temp_fn);

        $again = new MO();
        $again->import_from_file($temp_fn);

        $this->assertEquals(0, count($again->entries));
    }

    public function test_nplurals_with_backslashn()
    {
        $mo = new MO();
        $mo->import_from_file(__DIR__ . '/data/bad_nplurals.mo');
        $this->assertEquals('%d foro', $mo->translate_plural('%d forum', '%d forums', 1));
        $this->assertEquals('%d foros', $mo->translate_plural('%d forum', '%d forums', 2));
        $this->assertEquals('%d foros', $mo->translate_plural('%d forum', '%d forums', -1));
    }

    public function disabled_test_performance()
    {
        $start = microtime(true);
        $mo = new MO();
        $mo->import_from_file(__DIR__ . '/data/de_DE-2.8.mo');
        // echo "\nPerformance: ".(microtime(true) - $start)."\n";
    }

    public function test_overloaded_mb_functions()
    {
        if ((ini_get('mbstring.func_overload') & 2) == 0) {
            $this->markTestSkipped(__METHOD__ . ' only runs when mbstring.func_overload is enabled.');
        }

        $mo = new MO();
        $mo->import_from_file(__DIR__ . '/data/overload.mo');
        $this->assertEquals(array('Табло'), $mo->entries['Dashboard']->translations);
    }

    public function test_load_pot_file()
    {
        $mo = new MO();
        $this->assertEquals(false, $mo->import_from_file(__DIR__ . '/data/mo.pot'));
    }
}
