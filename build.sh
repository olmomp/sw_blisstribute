#!/bin/bash
##########################################
## BLISSTRIBUTE SHOPWARE RELEASE SCRIPT ##
######### (C) 2018 EXITB GMBH ############
########## https://exitb.de ##############
##########################################

ZIP_INTERPRETER=`which zip`
if [[ -z ${ZIP_INTERPRETER} ]]; then
    echo "you need to have ZIP installed"
    exit;
fi

# check run from instance root
BASE_DIR=`dirname "$0"`
if ! [[ "${BASE_DIR}" == "." ]]; then
    echo "you need to run this script from directory root"
    exit;
fi;

BUILD_DIR=`realpath ${BASE_DIR}"/build"`
# CLEAN OLDER RELEASES
rm -Rf ${BUILD_DIR}/*

# CREATE TARGET DIR STRUCTURE
SOURCE_DIR=${BUILD_DIR}/Backend/ExitBBlisstribute
mkdir -p ${SOURCE_DIR}

# EXCLUDES SHOULD BE FINE FOR NOW
EXCLUDES="--exclude=.git --exclude=.gitignore --exclude=build --exclude=.idea --exclude=build.sh"
rsync -avz ${EXCLUDES} ${BASE_DIR} ${SOURCE_DIR}

VERSION=`cat ${SOURCE_DIR}/plugin.json  |grep "currentVersion" |awk -F"[ \":]+" '/\"currentVersion\"\:/{print $3}'`

cd ${BUILD_DIR}
${ZIP_INTERPRETER} -r ${BUILD_DIR}/v${VERSION}.zip ./Backend/



