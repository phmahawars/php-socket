<?php 
// exec("php server.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audio Calling App</title>
</head>
<body>
    <h1>Audio Calling App</h1>
    <button id="startCall">Start Call</button>
    <button id="hangUp" disabled>Hang Up</button>

    <script>
        const socket = new WebSocket("ws://localhost:3000/socket");
        let localStream;
        let peerConnection;

        const configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' } // Google's public STUN server
            ]
        };

        socket.onopen = () => {
            console.log("Connected to WebSocket server");
        };

        socket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            switch(data.type) {
                case 'offer':
                    handleOffer(data.offer);
                    break;
                case 'answer':
                    handleAnswer(data.answer);
                    break;
                case 'candidate':
                    handleCandidate(data.candidate);
                    break;
            }
        };

        document.getElementById('startCall').onclick = async () => {
            localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            peerConnection = new RTCPeerConnection(configuration);
            localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));

            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    socket.send(JSON.stringify({ type: 'candidate', candidate: event.candidate }));
                }
            };

            peerConnection.ontrack = (event) => {
                const audio = new Audio();
                audio.srcObject = event.streams[0];
                audio.play();
            };

            const offer = await peerConnection.createOffer();
            await peerConnection.setLocalDescription(offer);
            socket.send(JSON.stringify({ type: 'offer', offer: offer }));
        };

        async function handleOffer(offer) {
            peerConnection = new RTCPeerConnection(configuration);
            peerConnection.setRemoteDescription(new RTCSessionDescription(offer));

            localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));

            const answer = await peerConnection.createAnswer();
            await peerConnection.setLocalDescription(answer);
            socket.send(JSON.stringify({ type: 'answer', answer: answer }));

            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    socket.send(JSON.stringify({ type: 'candidate', candidate: event.candidate }));
                }
            };

            peerConnection.ontrack = (event) => {
                const audio = new Audio();
                audio.srcObject = event.streams[0];
                audio.play();
            };
        }

        function handleAnswer(answer) {
            peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
        }

        function handleCandidate(candidate) {
            peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
        }

        document.getElementById('hangUp').onclick = () => {
            if (peerConnection) {
                peerConnection.close();
                peerConnection = null;
            }
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
            socket.close();
        };
    </script>
</body>
</html>
