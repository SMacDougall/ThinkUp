<?php
/**
 *
 * ThinkUp/webapp/_lib/model/class.OwnerMySQLDAO.php
 *
 * Copyright (c) 2009-2011 Gina Trapani
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkupapp.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 *
 * Owner Data Access Object
 * The data access object for retrieving and saving owners in the ThinkUp database.
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2009-2011 Gina Trapani
 * @author Gina Trapani <ginatrapani[at]gmail[dot]com>
 */
class OwnerMySQLDAO extends PDODAO implements OwnerDAO {

    public function getByEmail($email) {
        $q = <<<SQL
SELECT
    id,
    full_name,
    email,
    is_admin,
    last_login,
    is_activated,
    password_token,
    account_status,
    failed_logins,
    api_key
FROM #prefix#owners AS o
WHERE email = :email;
SQL;

        $vars = array(
            ':email'=>$email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return $this->getDataRowAsObject($ps, 'Owner');
    }

    public function getAllOwners() {
        $q = " SELECT id, full_name, email, is_admin, is_activated, last_login ";
        $q .= "FROM #prefix#owners ORDER BY last_login DESC;";
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q);
        return $this->getDataRowsAsObjects($ps, 'Owner');
    }

    public function getAdmins() {
        $q = " SELECT id, full_name, email, is_admin, is_activated, last_login ";
        $q .= "FROM #prefix#owners WHERE is_admin = 1 AND is_activated = 1 ORDER BY id";
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q);
        $admins = $this->getDataRowsAsObjects($ps, 'Owner');
        if (count($admins) == 0) { $admins = null; }
        return $admins;
    }

    public function doesOwnerExist($email) {
        $q = " SELECT email FROM #prefix#owners WHERE email=:email";
        $vars = array(
            ':email'=>$email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return $this->getDataIsReturned($ps);
    }

    public function getPass($email) {
        $q = "SELECT pwd FROM #prefix#owners  WHERE email = :email AND is_activated='1' LIMIT 1;";
        $vars = array(
            ':email'=>$email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        $result = $this->getDataRowAsArray($ps);
        if (isset($result['pwd'])) {
            return $result['pwd'];
        } else {
            return false;
        }
    }

    public function getActivationCode($email) {
        $q = " SELECT activation_code  FROM #prefix#owners  WHERE email=:email";
        $vars = array(
            ':email'=>$email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return $this->getDataRowAsArray($ps);
    }

    public function activateOwner($email) {
        $this->updateActivation($email, true);
    }

    public function deactivateOwner($email) {
        $this->updateActivation($email, false);
    }

    /**
     * Set the value of the is_activated field.
     * @param str $email
     * @param bool $is_activated
     * @return int Count of affected rows
     */
    private function updateActivation($email, $is_activated) {
        $q = " UPDATE #prefix#owners SET is_activated=:is_activated WHERE email=:email";
        $vars = array(
            ':email'=>$email,
            ':is_activated'=>(($is_activated)?1:0)
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return $this->getUpdateCount($ps);
    }

    public function updatePassword($email, $pwd) {
        $q = " UPDATE #prefix#owners SET pwd=:pwd WHERE email=:email";
        $vars = array(
            ':email'=>$email,
            ':pwd'=>$pwd
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return $this->getUpdateCount($ps);
    }

    public function create($email, $pass, $acode, $full_name) {
        return $this->createOwner($email, $pass, $acode, $full_name, false);
    }

    public function createAdmin($email, $pwd, $activation_code, $full_name) {
        return $this->createOwner($email, $pwd, $activation_code, $full_name, true);
    }

    private function createOwner($email, $pass, $acode, $full_name, $is_admin) {
        if (!$this->doesOwnerExist($email)) {
            $q = "INSERT INTO #prefix#owners SET email=:email, pwd=:pass, joined=NOW(), activation_code=:acode, " .
            "full_name=:full_name, api_key=:api_key";
            if ($is_admin) {
                $q .= ", is_admin=1";
            }
            $api_key = $this->generateAPIKey();
            $vars = array(
                ':email'=>$email,
                ':pass'=>$pass,
                ':acode'=>$acode,
                ':full_name'=>$full_name,
                ':api_key'=>$api_key
            );
            if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
            $ps = $this->execute($q, $vars);
            return $this->getUpdateCount($ps);
        } else {
            return 0;
        }
    }

    public function updateLastLogin($email) {
        $q = " UPDATE #prefix#owners SET last_login=now() WHERE email=:email";
        $vars = array(
            ':email'=>$email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return $this->getUpdateCount($ps);
    }

    public function updatePasswordToken($email, $token) {
        $q = "UPDATE #prefix#owners
              SET password_token=:token
              WHERE email=:email";
        $vars = array(
            ":token" => $token, 
            ":email" => $email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return $this->getUpdateCount($ps);
    }

    public function getByPasswordToken($token) {
        $q = "SELECT * FROM #prefix#owners WHERE password_token LIKE :token";
        $vars = array(':token' => $token . '_%');
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return $this->getDataRowAsObject($ps, 'Owner');
    }

    public function doesAdminExist() {
        $q = "SELECT id FROM #prefix#owners WHERE is_admin = 1";
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q);
        return $this->getDataIsReturned($ps);
    }

    public function promoteToAdmin($email) {
        $q = "UPDATE #prefix#owners
              SET is_admin=1
              WHERE email=:email";
        $vars = array(
            ":email" => $email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return $this->getUpdateCount($ps);
    }

    public function incrementFailedLogins($email) {
        $q = "UPDATE #prefix#owners
              SET failed_logins=failed_logins+1
              WHERE email=:email";
        $vars = array(
            ":email" => $email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return ( $this->getUpdateCount($ps) > 0 )? true : false;
    }

    public function resetFailedLogins($email) {
        $q = "UPDATE #prefix#owners
              SET failed_logins=0
              WHERE email=:email";
        $vars = array(
            ":email" => $email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return ( $this->getUpdateCount($ps) > 0 )? true : false;
    }

    public function setAccountStatus($email, $status) {
        $q = "UPDATE #prefix#owners
              SET account_status=:account_status
              WHERE email=:email";
        $vars = array(
            ":account_status" => $status,
            ":email" => $email
        );
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $ps = $this->execute($q, $vars);
        return ( $this->getUpdateCount($ps) > 0 )? true : false;
    }

    public function clearAccountStatus($email) {
        return  $this->setAccountStatus($email, '');
    }

    public function setOwnerActive($id, $is_activated) {
        $q = "UPDATE #prefix#owners
             SET is_activated=:is_activated
             WHERE id=:id";
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $stmt = $this->execute($q, array(':is_activated' => $is_activated, ':id' => $id));
        return $this->getUpdateCount($stmt);
    }

    public function resetAPIKey($id) {
        $q = "UPDATE #prefix#owners SET api_key=:api_key WHERE id=:id";
        if ($this->profiler_enabled) Profiler::setDAOMethod(__METHOD__);
        $new_api_key = $this->generateAPIKey();
        $stmt = $this->execute($q, array(':api_key' => $new_api_key, ':id' => $id));
        if ($this->getUpdateCount($stmt) == 0) {
            return false;
        } else {
            return $new_api_key;
        }
    }

    /**
     * Generate a new API KEY - md5 hashed random string
     * @return str A generated API Key
     */
    private function generateAPIKey() {
        return md5(uniqid(mt_rand(), true)); // generate random api key
    }
}