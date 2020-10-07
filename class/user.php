<?php

class User {
    private $userName;
    private $sshPublicKeyId;
    private $sshPublicKey;

    public function __construct($userName)
    {
        $this->userName = $userName;
    }

    private function loadSSHPublicKeyIdFromFile()
    {
        if (file_exists($filename = '/tmp/SSHPublicKeyIds/'.$this->userName)) {
            $this->sshPublicKeyId = file_get_contents($filename);
            return true;
        }

        return false;
    }

    public function loadSSHPublicKeyId()
    {
        if ($this->loadSSHPublicKeyIdFromFile()) {
            return $this;
        }

        $userPublicKeyInfoRaw = shell_exec(
            "aws iam list-ssh-public-keys --user-name ".$this->userName
        );

        $userPublicKeyInfo = json_decode($userPublicKeyInfoRaw);
        if (!$userPublicKeyInfo) {
            throw new Exception("User ".$this->userName." does not exist: $userPublicKeyInfoRaw");
        }

        $this->sshPublicKeyId = $userPublicKeyInfo->SSHPublicKeys[0]->SSHPublicKeyId;

        if (!is_dir('/tmp/SSHPublicKeyIds')) {
            mkdir('/tmp/SSHPublicKeyIds');
        }

        file_put_contents('/tmp/SSHPublicKeyIds/'.$this->userName, $this->sshPublicKeyId);

        return $this;
    }

    public function getSSHPublicKeyId()
    {
        if (null === $this->sshPublicKeyId) {
            $this->loadSSHPublicKeyId();
        }

        return $this->sshPublicKeyId;
    }
    
    public function loadSSHPublicKey()
    {
        $userSSHPublicKeyId = $this->getSSHPublicKeyId();
        if (!$userSSHPublicKeyId) {
            throw new Exception("User ".$this->userName."'s public key is not uploaded to AWS");
        }

        $userPublicKeyDetails = json_decode(
            shell_exec(
                "aws iam get-ssh-public-key ".
                "--user-name ".$this->userName." ".
                "--ssh-public-key-id $userSSHPublicKeyId ".
                "--encoding SSH"
            )
        );

        $publicKey = $userPublicKeyDetails->SSHPublicKey->SSHPublicKeyBody;
                                                          
        $this->sshPublicKey = $publicKey;
        return $this;
    }

    public function getSSHPublicKey() {
        if (null === $this->sshPublicKey) {
            $this->loadPublicKey();
        }

        return $this->sshPublicKey;
    }

    public function createHomeDirectoryForUser()
    {
        if (is_dir("/home/".$this->userName)) {
            return $this;
        }

        shell_exec('mkhomedir_helper '.$this->userName);
        return $this;
    }
}
