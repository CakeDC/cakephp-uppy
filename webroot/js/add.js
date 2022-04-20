document.querySelector('.Uppy').innerHTML = ''

var uppy = new Uppy.Core({ debug: debug, autoProceed: true })
uppy.use(Uppy.FileInput, {
    target: '.Uppy',
})
uppy.use(Uppy.ProgressBar, {
    target: '.UppyProgressBar',
    hideAfterFinish: false,
})
uppy.use(Uppy.AwsS3, {
    getUploadParameters (file) {
        let body = JSON.stringify({
                filename: file.name,
                contentType: file.type,
            });
        return fetch(signUrl, {
            method: 'post',
            headers: {
                accept: 'application/json',
                'content-type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: body,
        })                
        .then((response) => {                
            return response.json()
        }).then((data) => {
            if (data.error){                    
                document.querySelector('.uploaded-response').innerHTML = file_not_saved;                                         
            }else{
                if (data.code!=200&&data.message!== undefined){                     
                    document.querySelector('.uploaded-response').innerHTML = data.message;
                    return false;
                }
                return {
                    method: data.method,
                    url: data.url,
                    fields: data.fields,
                    headers: data.headers,
                }
            }
        })
    }
});            

uppy.on('upload-success', (file, response) => {

    const url = response.uploadURL
    const fileName = file.name

    const li = document.createElement('li')
    const p = document.createElement('p')
    p.appendChild(document.createTextNode(fileName))
    li.appendChild(p)

    document.querySelector('.uploaded-files ol').appendChild(li);

    let objs = [];
    let obj = {};        

    obj.filename = file.name;
    obj.filesize = file.size;
    obj.mime_type = file.type;
    obj.extension = file.extension;
    obj.foreign_key = document.querySelector('input[name="foreign_key"]').value;           
    obj.model = document.querySelector('input[name="model"]').value;
    let v = response.uploadURL.split('/')
    obj.path = v[v.length-1];
    objs[objs.length] = obj;

    let body = JSON.stringify({items: objs});
    fetch(saveUrl, {
        method: 'post',
        headers: {
            accept: 'application/json',
            'content-type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: body,
    })
    .then((resp) => resp.json())
    .then(function(data) {                                
        document.querySelector('.uploaded-response').innerHTML = data.result.message;                          
    })
    .catch(function(error) {
        document.querySelector('.uploaded-response').innerHTML = error.message;                          
    });
})