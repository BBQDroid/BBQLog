SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `changelog`
--
CREATE DATABASE `changelog` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `changelog`;

-- --------------------------------------------------------

--
-- Table structure for table `commits`
--

CREATE TABLE IF NOT EXISTS `commits` (
  `SHA` varchar(40) NOT NULL,
  `GitUsername` varchar(128) NOT NULL,
  `Repository` varchar(255) NOT NULL,
  `Branch` varchar(128) NOT NULL,
  `Author` varchar(50) NOT NULL,
  `Message` text NOT NULL,
  `GerritID` int(11) NOT NULL,
  `CommitDate` datetime NOT NULL,
  PRIMARY KEY (`SHA`,`GitUsername`,`Repository`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `repositories`
--

CREATE TABLE IF NOT EXISTS `repositories` (
  `IDRepository` int(11) NOT NULL AUTO_INCREMENT,
  `GitUsername` varchar(128) NOT NULL,
  `Repository` varchar(255) NOT NULL,
  `IDRomVersion` int(11) NOT NULL,
  `Branch` varchar(128) NOT NULL,
  PRIMARY KEY (`IDRepository`),
  UNIQUE KEY `GitUsername` (`GitUsername`,`Repository`,`Branch`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=349 ;

-- --------------------------------------------------------

--
-- Table structure for table `roms`
--

CREATE TABLE IF NOT EXISTS `roms` (
  `IDRom` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(128) NOT NULL,
  PRIMARY KEY (`IDRom`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

-- --------------------------------------------------------

--
-- Table structure for table `roms_versions`
--

CREATE TABLE IF NOT EXISTS `roms_versions` (
  `IDRomVersion` int(11) NOT NULL AUTO_INCREMENT,
  `IDRom` int(11) NOT NULL,
  `VersionName` varchar(50) NOT NULL,
  PRIMARY KEY (`IDRomVersion`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;
