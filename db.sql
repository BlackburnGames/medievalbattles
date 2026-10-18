#
# Table structure for table `barter`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `barter` (
  `seller` varchar(255) NOT NULL default '',
  `type` varchar(255) NOT NULL default '',
  `amount` bigint(255) NOT NULL default '0',
  `cost` bigint(255) NOT NULL default '0',
  `barterid` bigint(255) NOT NULL default '0',
  `userid` bigint(255) NOT NULL default '0',
  `method` varchar(255) NOT NULL default 'gp',
  `page` varchar(255) NOT NULL default '',
  `guild` varchar(255) NOT NULL default '',
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `barter`
#

# --------------------------------------------------------

#
# Table structure for table `buildings`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `buildings` (
  `amts` bigint(255) NOT NULL default '0',
  `aland` bigint(255) NOT NULL default '0',
  `home` bigint(255) NOT NULL default '0',
  `kennel` bigint(255) NOT NULL default '0',
  `barrack` bigint(255) NOT NULL default '0',
  `farm` bigint(255) NOT NULL default '0',
  `wp` bigint(255) NOT NULL default '0',
  `lmill` bigint(255) NOT NULL default '0',
  `gm` bigint(255) NOT NULL default '0',
  `im` bigint(255) NOT NULL default '0',
  `dhome` bigint(255) NOT NULL default '0',
  `dbarrack` bigint(255) NOT NULL default '0',
  `dfarm` bigint(255) NOT NULL default '0',
  `dwp` bigint(255) NOT NULL default '0',
  `dlmill` bigint(255) NOT NULL default '0',
  `dgm` bigint(255) NOT NULL default '0',
  `dim` bigint(255) NOT NULL default '0',
  `Hhrs` bigint(255) NOT NULL default '0',
  `Bhrs` bigint(255) NOT NULL default '0',
  `Fhrs` bigint(255) NOT NULL default '0',
  `Whrs` bigint(255) NOT NULL default '0',
  `Lhrs` bigint(255) NOT NULL default '0',
  `Ghrs` bigint(255) NOT NULL default '0',
  `Ihrs` bigint(255) NOT NULL default '0',
  `userid` bigint(255) NOT NULL default '0',
  KEY `userid` (`userid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `buildings`
#


# --------------------------------------------------------

#
# Table structure for table `empnews`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `empnews` (
  `date` varchar(255) NOT NULL default '',
  `news` blob NOT NULL,
  `class` varchar(16) NOT NULL default 'yellow',
  `yourid` bigint(255) NOT NULL default '0',
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `empnews`
#


# --------------------------------------------------------

#
# Table structure for table `explore`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `explore` (
  `userid` bigint(20) NOT NULL default '0',
  `expland` bigint(20) NOT NULL default '0',
  `expmt` bigint(20) NOT NULL default '0',
  `landhrs` bigint(255) NOT NULL default '0',
  `mthrs` bigint(255) NOT NULL default '0',
  KEY `userid` (`userid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `explore`
#


# --------------------------------------------------------

#
# Table structure for table `friends`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `friends` (
  `useremail` varchar(200) NOT NULL default '0',
  `friendid` int(11) NOT NULL default '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `friends`
#

# --------------------------------------------------------

#
# Table structure for table `game_info`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `game_info` (
  `lastset` bigint(255) NOT NULL default '0',
  `tick` varchar(255) NOT NULL default '',
  `last_comp_id` varchar(255) NOT NULL default '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `game_info`
#

INSERT INTO `game_info` VALUES (0, 'no', '998');
# --------------------------------------------------------

#
# Table structure for table `guild`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `guild` (
  `gname` varchar(255) NOT NULL default '',
  `cpw` varchar(255) NOT NULL default '',
  `strength` bigint(255) NOT NULL default '0',
  `gid` bigint(255) NOT NULL default '0',
  `datemade` varchar(255) NOT NULL default '',
  `info` blob NOT NULL,
  -- A URL the guild leader sets, rendered as the guild's badge in the gc.php
  -- listing. guildconfig.php has always read and written it and S_GCONFIG.php
  -- has always offered the input; only this column was missing, so the feature
  -- looked cut when it was merely unsaveable. See docs/modernization.md.
  `flag` varchar(255) NOT NULL default '',
  `owner` varchar(255) NOT NULL default '',
  `mem` bigint(20) NOT NULL default '0',
  `notice` varchar(255) NOT NULL default 'None',
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `guild`
#

# --------------------------------------------------------

#
# Table structure for table `guildmsgs`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `guildmsgs` (
  `messageid` smallint(6) NOT NULL auto_increment,
  `name` varchar(30) NOT NULL default '',
  `host` varchar(50) NOT NULL default '',
  `topic` varchar(60) NOT NULL default '',
  `topicid` smallint(6) NOT NULL default '0',
  `message` text NOT NULL,
  `datestamp` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`messageid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `guildmsgs`
#

# --------------------------------------------------------

#
# Table structure for table `guildnews`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `guildnews` (
  `date` varchar(255) NOT NULL default '',
  `news` varchar(255) NOT NULL default '',
  `class` varchar(16) NOT NULL default 'yellow',
  `gnid` bigint(255) NOT NULL default '0',
  `age` bigint(255) NOT NULL default '0',
  `gid` varchar(225) NOT NULL default '',
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `guildnews`
#


# --------------------------------------------------------

#
# Table structure for table `guildrequests`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `guildrequests` (
  `applicant` varchar(255) NOT NULL default '',
  `gl_userid` varchar(255) NOT NULL default '',
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `guildrequests`
#


# --------------------------------------------------------

#
# Table structure for table `guildthreads`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `guildthreads` (
  `topicid` smallint(6) NOT NULL auto_increment,
  `name` varchar(30) NOT NULL default '',
  `host` varchar(50) NOT NULL default '',
  `topic` varchar(60) NOT NULL default '',
  `lastpost` varchar(20) NOT NULL default '',
  `lastposter` varchar(255) NOT NULL default '',
  `replies` smallint(6) NOT NULL default '0',
  `message` text NOT NULL,
  `datestamp` varchar(20) NOT NULL default '',
  `guildname` varchar(100) NOT NULL default '',
  PRIMARY KEY  (`topicid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `guildthreads`
#


# --------------------------------------------------------

#
# Table structure for table `ip_addresses`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `ip_addresses` (
  `ip` varchar(255) NOT NULL default ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `ip_addresses`
#

# --------------------------------------------------------

#
# Table structure for table `messages`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `messages` (
  `origin` varchar(255) NOT NULL default '',
  `datesent` varchar(255) NOT NULL default '',
  `message` blob NOT NULL,
  `yourid` bigint(255) NOT NULL default '0',
  `mid` bigint(255) NOT NULL default '0',
  `age` bigint(255) NOT NULL default '0',
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `messages`
#


# --------------------------------------------------------

#
# Table structure for table `military`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `military` (
  `warriors` bigint(255) unsigned NOT NULL default '0',
  `wizards` bigint(255) unsigned NOT NULL default '0',
  `priests` bigint(255) unsigned NOT NULL default '0',
  `archers` bigint(255) unsigned NOT NULL default '0',
  `dbwar` bigint(255) NOT NULL default '0',
  `dbwiz` bigint(255) NOT NULL default '0',
  `dbpri` bigint(255) NOT NULL default '0',
  `dbwar2` bigint(255) NOT NULL default '0',
  `dbwiz2` bigint(255) NOT NULL default '0',
  `dbpri2` bigint(255) NOT NULL default '0',
  `dbarch` bigint(255) NOT NULL default '0',
  `dbarch2` bigint(255) NOT NULL default '0',
  `explorers` bigint(255) NOT NULL default '0',
  `scientists` bigint(255) NOT NULL default '0',
  `thieves` bigint(255) NOT NULL default '0',
  `sages` bigint(255) NOT NULL default '0',
  `recruits` bigint(255) NOT NULL default '0',
  `maxciv` bigint(255) NOT NULL default '0',
  `userid` bigint(255) NOT NULL default '0',
  `civ` bigint(255) NOT NULL default '0',
  `warriorwep` tinyint(3) NOT NULL default '0',
  `priestwep` tinyint(3) NOT NULL default '0',
  `archerwep` tinyint(3) NOT NULL default '0',
  `warriorarm` tinyint(3) NOT NULL default '0',
  `wizardarm` tinyint(3) NOT NULL default '0',
  `priestarm` tinyint(3) NOT NULL default '0',
  `shortsword` tinyint(12) NOT NULL default '0',
  `longsword` tinyint(12) NOT NULL default '0',
  `bastardsword` tinyint(12) NOT NULL default '0',
  `scourge` tinyint(12) NOT NULL default '0',
  `scimitar` tinyint(12) NOT NULL default '0',
  `romsfury` tinyint(12) NOT NULL default '0',
  `broadsword` tinyint(12) NOT NULL default '0',
  `gandalara` tinyint(12) NOT NULL default '0',
  `mace` tinyint(12) NOT NULL default '0',
  `flail` tinyint(12) NOT NULL default '0',
  `zakarum` tinyint(12) NOT NULL default '0',
  `footmanflail` tinyint(12) NOT NULL default '0',
  `morningstar` tinyint(12) NOT NULL default '0',
  `thyorastear` tinyint(12) NOT NULL default '0',
  `isidole` tinyint(12) NOT NULL default '0',
  `eldamarstar` tinyint(12) NOT NULL default '0',
  `shortbow` tinyint(12) NOT NULL default '0',
  `ferricbow` tinyint(12) NOT NULL default '0',
  `keldarsarms` tinyint(12) NOT NULL default '0',
  `splensight` tinyint(12) NOT NULL default '0',
  `bowoftion` tinyint(12) NOT NULL default '0',
  `dynefian` tinyint(12) NOT NULL default '0',
  `heartsong` tinyint(12) NOT NULL default '0',
  `shyrscreamsbow` tinyint(12) NOT NULL default '0',
  `cweapon` varchar(255) NOT NULL default 'None',
  `cspell` varchar(255) NOT NULL default 'None',
  `cstaff` varchar(255) NOT NULL default 'None',
  `cbow` varchar(255) NOT NULL default 'None',
  `wararmor` varchar(255) NOT NULL default 'None',
  `wizarmor` varchar(255) NOT NULL default 'None',
  `priarmor` varchar(255) NOT NULL default 'None',
  `archarmor` varchar(255) NOT NULL default 'None',
  `cs` tinyint(12) NOT NULL default '0',
  `cm` tinyint(12) NOT NULL default '0',
  `bp` tinyint(12) NOT NULL default '0',
  `fp` tinyint(12) NOT NULL default '0',
  `ma` tinyint(1) NOT NULL default '0',
  `ba` tinyint(1) NOT NULL default '0',
  `ga` tinyint(1) NOT NULL default '0',
  `tr` tinyint(12) NOT NULL default '0',
  `mr` tinyint(12) NOT NULL default '0',
  `warspeedw` bigint(255) NOT NULL default '0',
  `wizspeeds` bigint(255) NOT NULL default '0',
  `prispeedw` bigint(255) NOT NULL default '0',
  `archspeedw` bigint(255) NOT NULL default '0',
  `warspeeda` bigint(255) NOT NULL default '0',
  `wizspeeda` bigint(255) NOT NULL default '0',
  `prispeeda` bigint(255) NOT NULL default '0',
  `archspeeda` bigint(255) NOT NULL default '0',
  `warpower` bigint(255) NOT NULL default '0',
  `wizpower` bigint(255) NOT NULL default '0',
  `pripower` bigint(255) NOT NULL default '0',
  `archpower` bigint(255) NOT NULL default '0',
  `wardef` bigint(255) NOT NULL default '0',
  `wizdef` bigint(255) NOT NULL default '0',
  `pridef` bigint(255) NOT NULL default '0',
  `archdef` bigint(255) NOT NULL default '0',
  `dbexplorer` bigint(255) NOT NULL default '0',
  `dbthief` bigint(255) NOT NULL default '0',
  `dbsage` bigint(255) NOT NULL default '0',
  `dbscientist` bigint(255) NOT NULL default '0',
  `suicide` bigint(255) NOT NULL default '0',
  `dbsuicide` bigint(255) NOT NULL default '0',
  `dbsuicide2` bigint(255) NOT NULL default '0',
  `dbsuicide3` bigint(255) NOT NULL default '0',
  `catapult` bigint(255) NOT NULL default '0',
  `dbcatapult` bigint(255) NOT NULL default '0',
  `dbcatapult2` bigint(255) NOT NULL default '0',
  `dbcatapult3` bigint(255) NOT NULL default '0',
  `golem` bigint(255) NOT NULL default '0',
  `dbgolem` bigint(255) NOT NULL default '0',
  `irongolem` bigint(255) NOT NULL default '0',
  `dbirongolem` bigint(255) NOT NULL default '0',
  KEY `userid` (`userid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `military`
#


# --------------------------------------------------------

#
# Table structure for table `research`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `research` (
  `userid` bigint(255) NOT NULL default '0',
  `r1` bigint(255) NOT NULL default '0',
  `r2` bigint(255) NOT NULL default '0',
  `r3` bigint(255) NOT NULL default '0',
  `r4` bigint(255) NOT NULL default '0',
  `r5` bigint(255) NOT NULL default '0',
  `r6` bigint(255) NOT NULL default '0',
  `r7` bigint(255) NOT NULL default '0',
  `r8` bigint(255) NOT NULL default '0',
  `r9` bigint(255) NOT NULL default '0',
  `r10` bigint(255) NOT NULL default '0',
  `r11` bigint(255) NOT NULL default '0',
  `r12` bigint(255) NOT NULL default '0',
  `r13` bigint(255) NOT NULL default '0',
  `r14` bigint(255) NOT NULL default '0',
  -- The game has eighteen research projects, not fourteen: r15 Advanced
  -- Farming, r16 Advanced Construction, r17 Animation, r18 Advanced Animation.
  -- cresearch.php assigns sages to all eighteen and functions.php, buildings.php,
  -- animate.php and attack.php all read their point totals; only this file
  -- stopped at fourteen. Because the tick adds points to r1pts-r18pts in a
  -- single atomic UPDATE, the four missing columns meant NO research point ever
  -- accrued. See docs/modernization.md.
  `r15` bigint(255) NOT NULL default '0',
  `r16` bigint(255) NOT NULL default '0',
  `r17` bigint(255) NOT NULL default '0',
  `r18` bigint(255) NOT NULL default '0',
  `r1pts` bigint(255) NOT NULL default '0',
  `r2pts` bigint(255) NOT NULL default '0',
  `r3pts` bigint(255) NOT NULL default '0',
  `r4pts` bigint(255) NOT NULL default '0',
  `r5pts` bigint(255) NOT NULL default '0',
  `r6pts` bigint(255) NOT NULL default '0',
  `r7pts` bigint(255) NOT NULL default '0',
  `r8pts` bigint(255) NOT NULL default '0',
  `r9pts` bigint(255) NOT NULL default '0',
  `r10pts` bigint(255) NOT NULL default '0',
  `r11pts` bigint(255) NOT NULL default '0',
  `r12pts` bigint(255) NOT NULL default '0',
  `r13pts` bigint(255) NOT NULL default '0',
  `r14pts` bigint(255) NOT NULL default '0',
  `r15pts` bigint(255) NOT NULL default '0',
  `r16pts` bigint(255) NOT NULL default '0',
  `r17pts` bigint(255) NOT NULL default '0',
  `r18pts` bigint(255) NOT NULL default '0',
  KEY `userid` (`userid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `research`
#


# --------------------------------------------------------

#
# Table structure for table `returntbl`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `returntbl` (
  `userid` bigint(255) NOT NULL default '0',
  `war1` bigint(255) NOT NULL default '0',
  `war2` bigint(255) NOT NULL default '0',
  `war3` bigint(255) NOT NULL default '0',
  `war4` bigint(255) NOT NULL default '0',
  `wiz1` bigint(255) NOT NULL default '0',
  `wiz2` bigint(255) NOT NULL default '0',
  `wiz3` bigint(255) NOT NULL default '0',
  `wiz4` bigint(255) NOT NULL default '0',
  `pri1` bigint(255) NOT NULL default '0',
  `pri2` bigint(255) NOT NULL default '0',
  `pri3` bigint(255) NOT NULL default '0',
  `pri4` bigint(255) NOT NULL default '0',
  `arch1` bigint(255) NOT NULL default '0',
  `arch2` bigint(255) NOT NULL default '0',
  `arch3` bigint(255) NOT NULL default '0',
  `arch4` bigint(255) NOT NULL default '0',
  -- Golems march with every army the engine sends: attack/calculations.php
  -- writes golem<n>/irongolem<n> here, update.php:179-191 reads them back into
  -- military, and functions.php renders them on the status screen. They were
  -- simply missing from this file, which made both of those UPDATEs fail --
  -- and a MySQL UPDATE is atomic, so the warriors, wizards, priests and
  -- archers named alongside them were not written either. Every army sent out
  -- was lost. See docs/modernization.md.
  `golem1` bigint(255) NOT NULL default '0',
  `golem2` bigint(255) NOT NULL default '0',
  `golem3` bigint(255) NOT NULL default '0',
  `golem4` bigint(255) NOT NULL default '0',
  `irongolem1` bigint(255) NOT NULL default '0',
  `irongolem2` bigint(255) NOT NULL default '0',
  `irongolem3` bigint(255) NOT NULL default '0',
  `irongolem4` bigint(255) NOT NULL default '0',
  `time1` bigint(255) NOT NULL default '0',
  `time2` bigint(255) NOT NULL default '0',
  `time3` bigint(255) NOT NULL default '0',
  `time4` bigint(255) NOT NULL default '0',
  `dogs` bigint(255) NOT NULL default '0',
  `dogtime` bigint(255) NOT NULL default '0',
  KEY `userid` (`userid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `returntbl`
#


# --------------------------------------------------------

#
# Table structure for table `setforums`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `setforums` (
  `setid` int(10) unsigned NOT NULL default '0',
  `topicid` smallint(6) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `topic` varchar(60) NOT NULL default '',
  `lastpost` varchar(20) NOT NULL default '',
  `lastposter` varchar(255) NOT NULL default '',
  `replies` smallint(6) NOT NULL default '0',
  `message` blob NOT NULL,
  `datestamp` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`topicid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `setforums`
#


# --------------------------------------------------------

#
# Table structure for table `setforumsmsgs`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `setforumsmsgs` (
  `setid` int(10) unsigned NOT NULL default '0',
  `messageid` smallint(6) NOT NULL auto_increment,
  `name` blob NOT NULL,
  `topic` varchar(60) NOT NULL default '',
  `topicid` smallint(6) NOT NULL default '0',
  `message` text NOT NULL,
  `datestamp` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`messageid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `setforumsmsgs`
#


# --------------------------------------------------------

#
# Table structure for table `setnews`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `setnews` (
  `date` varchar(255) NOT NULL default '',
  `news` blob NOT NULL,
  `class` varchar(16) NOT NULL default 'yellow',
  `setid` bigint(255) NOT NULL default '0',
  `sid` bigint(255) NOT NULL default '0',
  `age` bigint(255) NOT NULL default '0',
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `setnews`
#


# --------------------------------------------------------

#
# Table structure for table `settlement`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `settlement` (
  `members` tinyint(2) NOT NULL default '0',
  `setname` varchar(255) NOT NULL default 'None',
  `setpic` varchar(255) NOT NULL default '',
  `setnotice` varchar(255) NOT NULL default 'Welcome to Medieval Battles',
  `setid` bigint(255) NOT NULL auto_increment,
  `setstrength` bigint(255) NOT NULL default '0',
  PRIMARY KEY  (`setid`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Insert data for table `settlement`
#

-- Round 1's ten, as the baseline seeded them before rounds existed.
INSERT INTO settlement (round_id) VALUES (1), (1), (1), (1), (1), (1), (1), (1), (1), (1);


# --------------------------------------------------------

#
# Table structure for table `user`
#
# Creation: Jun 11, 2003 at 01:43 PM
# Last update: Apr 27, 2015 at 10:24 AM
#

CREATE TABLE `user` (
  `ename` varchar(255) NOT NULL default '',
  `race` varchar(255) NOT NULL default '',
  `class` varchar(255) NOT NULL default '',
  `gp` bigint(255) NOT NULL default '0',
  `iron` bigint(255) NOT NULL default '0',
  `lumber` bigint(255) NOT NULL default '0',
  `exp` bigint(255) NOT NULL default '0',
  `food` bigint(255) NOT NULL default '0',
  `land` bigint(255) NOT NULL default '0',
  `mts` bigint(255) NOT NULL default '0',
  `setid` bigint(255) NOT NULL default '0',
  `csnum` bigint(255) NOT NULL default '0',
  `userid` bigint(255) NOT NULL auto_increment,
  `online` bigint(255) NOT NULL default '0',
  `sl` varchar(15) NOT NULL default '',
  `vote` bigint(255) NOT NULL default '0',
  `votefor` varchar(255) NOT NULL default 'None',
  `exp2` bigint(255) NOT NULL default '0',
  `aim` varchar(255) NOT NULL default '',
  `mno` bigint(255) NOT NULL default '0',
  `nno` bigint(255) NOT NULL default '0',
  `fleets` tinyint(1) NOT NULL default '4',
  `msn` varchar(255) NOT NULL default '',
  `ip` varchar(255) NOT NULL default '',
  `lastlog` varchar(255) NOT NULL default 'N/A',
  `safemode` bigint(2) NOT NULL default '30',
  `lastlogin` varchar(20) NOT NULL default '',
  `countdown` int(3) NOT NULL default '336',
  `guild` varchar(255) NOT NULL default 'None',
  `barterclock` int(3) NOT NULL default '336',
  `current_comp_id` varchar(255) NOT NULL default '',
  `signup_comp_id` varchar(255) NOT NULL default '',
  -- The account this kingdom belongs to (the `account` table, below). The
  -- round holds no address or password of its own.
  `account_id` int unsigned NOT NULL default '0',
  PRIMARY KEY  (`userid`),
  KEY `account_id` (`account_id`),
  -- The round this row belongs to (the `round` table, below).
  `round_id` int unsigned NOT NULL,
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Dumping data for table `user`
#

# --------------------------------------------------------

#
# Table structure for table `account`
#
-- Who a player is, kept apart from what they played: the one table that
-- outlasts every round, so that ending one never has to delete a login. See
-- docs/rounds.md, "The model". It had a database of its own while each round
-- was going to be one.
--
-- An account is linked to its kingdom by `user.account_id`. The round's
-- tables hold neither the address nor the password: the game finds a kingdom
-- by its userid, and each of these exists once, here.

CREATE TABLE `account` (
  `account_id` int unsigned NOT NULL AUTO_INCREMENT,
  -- mb_valid_email() refuses anything longer than this column.
  `email` varchar(254) NOT NULL,
  -- The player's public name, the same in every round. Rankings and round
  -- history show the kingdom's name, not this (docs/rounds.md). Chosen at
  -- signup, unique ignoring case, held to the empire-name rule by
  -- mb_valid_handle(). Never the address, which is private.
  `handle` varchar(25) NOT NULL,
  -- bcrypt, or the legacy unsalted MD5 that checklogin.php rehashes on the
  -- way through. Bound in every query that touches it, never interpolated.
  `pw` varchar(255) NOT NULL,
  -- May use app/admin/. Granted from the command line: bin/admin.php.
  `admin` tinyint(1) NOT NULL DEFAULT 0,
  -- The browser id index.php issued when the account was made, as the
  -- round's `user.signup_comp_id` records it for that kingdom.
  `signup_comp_id` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  -- When the address was confirmed by following the mailed link; NULL until
  -- then, and the game's pages refuse an unverified account. It lived in the
  -- round's `emailvalidate` table, keyed by userid, which meant confirming an
  -- address once per round and losing the confirmation with the kingdom.
  `verified_at` datetime NULL DEFAULT NULL,
  -- The code the link carries: 32 hex characters, compared with hash_equals().
  `verify_code` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `handle` (`handle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

# --------------------------------------------------------

#
# Table structure for table `round`
#
-- A round of the game: a world that starts, runs and ends, while the accounts
-- outlast it. Every table above whose rows belong to one -- the kingdom and
-- everything hanging off it, settlements, guilds, messages and news --
-- carries its `round_id`. See docs/rounds.md, "Where a round lives".
--
-- Ids issued across rounds stay unique: userid, setid, gid and the rest are
-- never reused by a later round, so a query that filters on one of them is
-- already confined to its round. A query that does not -- a ranking, a
-- listing, the tick's whole-table updates, a lookup by name -- filters on
-- round_id.

CREATE TABLE `round` (
  `round_id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  -- Which rules the round plays by (docs/rulesets.md). Only 'classic' exists.
  `ruleset` varchar(32) NOT NULL DEFAULT 'classic',
  -- 'open' takes signups but does not tick; 'running' ticks; 'ended' is
  -- history. One round runs at a time until docs/rounds.md step 4.
  `status` enum('open','running','ended') NOT NULL DEFAULT 'open',
  `started_at` datetime NULL DEFAULT NULL,
  `ended_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A fresh install starts with round 1 running, as the migration makes the
-- world it finds into round 1.
INSERT INTO `round` (round_id, name, status) VALUES (1, 'Round 1', 'running');

# --------------------------------------------------------

#
# Table structure for table `round_result`
#
-- Each kingdom as it stood when its round ended: the history, written by
-- mb_round_end() (app/include/rounds.php). It is small and outlives the
-- round's own rows, which may be deleted by round_id once nothing wants more
-- than this.
--
-- Ranked by `exp`, which despite its name is the tick's networth
-- (docs/rounds.md). Land, mountains, settlement and guild are kept so the
-- other rankings can be rebuilt; a settlement's or a guild's strength is the
-- sum of its rows' `exp`, as the tick computes it. The kingdom's name, not
-- the account's handle, is what a round is remembered by; `account_id` lets
-- a player's own history list their kingdoms.

CREATE TABLE `round_result` (
  `round_id` int unsigned NOT NULL,
  `userid` bigint NOT NULL,
  `account_id` int unsigned NOT NULL,
  `ename` varchar(255) NOT NULL,
  `race` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `setid` bigint NOT NULL,
  -- The settlement's name then; settlements are renamed by their leaders.
  `setname` varchar(255) NOT NULL,
  `guild` varchar(255) NOT NULL,
  `land` bigint NOT NULL,
  `mts` bigint NOT NULL,
  `exp` bigint NOT NULL,
  -- 1 for the highest exp; ties go to the lower userid, the earlier signup.
  `place` int unsigned NOT NULL,
  PRIMARY KEY (`round_id`, `userid`),
  KEY `account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
