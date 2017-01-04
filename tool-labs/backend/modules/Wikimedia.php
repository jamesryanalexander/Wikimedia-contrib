<?php
require_once(__DIR__ . '/../models/Wiki.php');

/**
 * Manages data about Wikimedia wikis.
 */
class Wikimedia
{
    ##########
    ## Properties
    ##########
    /**
     * The underlying Wikimedia wiki and database data.
     * @var Wiki[]
     */
    private $wikis = null;


    ##########
    ## Public methods
    ##########
    /**
     * Construct a Wikimedia instance.
     * @param Database $db The database with which to connect to the database.
     * @param Cacher $cache The cache with which to read and write cached data.
     */
    public function __construct($db, $cache)
    {
        $this->wikis = $cache->get('wikimedia-wikis');
        if (!$this->wikis) {
            // build wiki list
            $this->wikis = [];
            $db->connect('metawiki.labsdb', 'metawiki_p');
            foreach ($db->query('SELECT dbname, lang, family, url, size, is_closed, slice FROM meta_p.wiki WHERE url IS NOT NULL')->fetchAllAssoc() as $row) {
                if ($row['dbname'] == 'votewiki')
                    continue; // DB schema is broken
                $this->wikis[$row['dbname']] = new Wiki($row['dbname'], $row['lang'], $row['family'], $row['url'], $row['size'], $row['is_closed'], $row['slice']);
            }

            // cache result
            if (count($this->wikis)) // if the fetch failed, we *don't* want to cache the result for a full day
                $cache->save('wikimedia-wikis', $this->wikis);
            $db->connectPrevious();
        }
    }

    /**
     * Get a database name => wiki lookup.
     * @return Wiki[]
     */
    public function getWikis()
    {
        return $this->wikis;
    }

    /**
     * Get the data for a wiki.
     * @param string $dbname The wiki's unique database name.
     * @return Wiki
     */
    public function getWiki($dbname)
    {
        if (array_key_exists($dbname, $this->wikis))
            return $this->wikis[$dbname];
        return null;
    }

    /**
     * Get the domain for a database name.
     * @param string $dbname The database name to find.
     * @return string|null
     */
    public function getDomain($dbname)
    {
        $wiki = $this->getWiki($dbname);
        return $wiki != null ? $wiki->domain : null;
    }

    /**
     * Get the host name for a database name.
     * @param string $dbname The database name to find.
     * @return string|null
     */
    public function getHost($dbname)
    {
        $wiki = $this->getWiki($dbname);
        return $wiki != null ? $wiki->host : null;
    }

    /**
     * Get a database name => domain lookup.
     * @param bool $includeClosed Whether to include closed wikis.
     * @return array
     */
    public function getDomains($includeClosed = false)
    {
        $wikis = array();
        foreach ($this->wikis as $wiki) {
            if ($includeClosed || !$wiki->isClosed)
                $wikis[$wiki->dbName] = $wiki->domain;
        }
        asort($wikis);
        return $wikis;
    }
}
