const Client = require("ftp");
const fs = require("fs");
const path = require("path");

const args = process.argv.slice(2).reduce((accumulatedArguments, arg) => {
    const split = arg.split("=");
    accumulatedArguments[split[0]] = split[1];
    return accumulatedArguments;
}, {});
const host = args.host || "wattyrev.com";
const user = args.user;
const password = args.pass;
const directoryPath = args.path;
const contentDirectory = args.dist || "dist";

// Create the FTP client
const client = new Client();

/**
 * Promise wrapper for client.mkdir. Creates a directory on the FTP server.
 * @param  {Client} client FTP client
 * @param  {String} name   The name of the folder to create
 * @return {Promise}
 */
function mkdir(client, name) {
    return new Promise((resolve, reject) => {
        client.mkdir(name, error => {
            if (error) {
                return reject(error);
            }
            return resolve();
        });
    });
}

/**
 * Promise wrapper for client.cwd. Moves into a directory on the FTP server.
 * @param  {Client} client FTP client
 * @param  {String} name The name of the folder to enter
 * @return {Promise}
 */
function cwd(client, name) {
    return new Promise((resolve, reject) => {
        client.cwd(name, (error, currentDirectory) => {
            if (error) {
                return reject(error);
            }
            return resolve(currentDirectory);
        });
    });
}

/**
 * Promise wrapper for client.list. Provides all the items in the current remote directory
 * @param  {Client} client FTP client
 * @return {Promise} Resolves with an array of items
 */
function list(client) {
    return new Promise((resolve, reject) => {
        client.list((error, list) => {
            if (error) {
                return reject(error);
            }
            return resolve(list);
        });
    });
}

/**
 * Promise wrapper for client.put. Uploads a file to the FTP server.
 * @param  {Client} client FTP client
 * @param  {String} itemToUpload The path to the file to be uploaded
 * @param  {String} remoteFileName The name for the file to be uploaded with into the current remote
 * directory
 * @return {Promise}
 */
function put(client, itemToUpload, remoteFileName) {
    return new Promise((resolve, reject) => {
        client.put(itemToUpload, remoteFileName, error => {
            if (error) {
                return reject(error);
            }
            return resolve();
        });
    });
}

/**
 * Upload the contents of the specified folder
 * @param  {Client} client The FTP client
 * @param  {String} folderToUpload The folder that should be uploaded
 * @return {Promise}
 */
async function uploadContents(client, folderToUpload) {
    // Get the items in the current local directory
    const items = fs.readdirSync(folderToUpload);

    // Get the list of items in the remote server
    const remoteList = await list(client);
    const uploadPromises = items.map(async item => {
        const remoteItem = remoteList.find(
            remoteItem => remoteItem.name === item
        );
        const pathToItem = path.join(folderToUpload, item);
        const localItemStats = fs.lstatSync(pathToItem);
        const isDirectory = localItemStats.isDirectory();

        if (isDirectory) {
            const directoryExists = !!remoteItem;
            if (!directoryExists) {
                console.log(`Creating directory ${item}`);
                await mkdir(client, item);
            }
            await cwd(client, item);
            return uploadContents(client, pathToItem);
        }

        const remoteItemSize = remoteItem ? remoteItem.size : 0;
        const localItemSize = localItemStats.size;
        if (remoteItemSize === localItemSize) {
            console.log(`No changes made to ${pathToItem}. Skipping upload.`);
            return new Promise(resolve => resolve());
        }

        console.log(`Uploading ${pathToItem} ...`);
        return put(client, pathToItem, item).then(() => {
            console.log(`Uploaded ${pathToItem}.`);
        });
    });
    return Promise.all(uploadPromises);
}

// When the client is ready, move in to the relevant folder, and begin the upload.
client.on("ready", function() {
    client.cwd(directoryPath, (error, currentDirectory) => {
        uploadContents(client, contentDirectory)
            .then(() => client.end())
            .catch(error => {
                throw error;
            });
    });
});

// Connect to the FTP server
client.connect({
    host,
    port: 21,
    user,
    password
});
