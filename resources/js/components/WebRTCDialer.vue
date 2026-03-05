<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Phone, PhoneOff, Mic, MicOff, X, Delete } from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { usePage } from '@inertiajs/vue3';
import { AppPageProps } from '@/types';
import axios from 'axios';
// @ts-ignore
import { UserAgent, Invitation, Inviter, SessionState, Registerer, RegistererState } from 'sip.js';

const dialpadOpen = ref(false);
const phoneNumber = ref('');
const isCallActive = ref(false);
const isMuted = ref(false);
const callDuration = ref('00:00');
const connectionStatus = ref('Disconnected');
const activeTab = ref<'keypad' | 'recent'>('keypad');
const callDirection = ref<'outbound' | 'inbound' | null>(null);
const recentCalls = ref<any[]>([]);
const isLoadingHistory = ref(true);

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth?.user);
const asteriskConfig = computed(() => page.props.asterisk);

let userAgent: UserAgent | null = null;
let currentSession: Inviter | Invitation | null = null;
let durationInterval: ReturnType<typeof setInterval> | null = null;
let durationSeconds = 0;

const toggleDialpad = () => {
  dialpadOpen.value = !dialpadOpen.value;
  if (dialpadOpen.value && !userAgent && user.value?.sip_extension && user.value?.sip_password) {
    initializeSIP();
  }
};

const closeDialpad = () => {
  dialpadOpen.value = false;
};

const appendNumber = (num: string) => {
  phoneNumber.value += num;
};

const removeNumber = () => {
  phoneNumber.value = phoneNumber.value.slice(0, -1);
};

const clearNumber = () => {
  phoneNumber.value = '';
};

const formatDuration = (seconds: number) => {
  const m = Math.floor(seconds / 60).toString().padStart(2, '0');
  const s = (seconds % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
};

const startDurationTimer = () => {
  durationSeconds = 0;
  callDuration.value = '00:00';
  durationInterval = setInterval(() => {
    durationSeconds++;
    callDuration.value = formatDuration(durationSeconds);
  }, 1000);
};

const stopDurationTimer = () => {
  if (durationInterval) clearInterval(durationInterval);
};

const setupRemoteAudio = (session: any) => {
  let remoteAudio = document.getElementById('remoteAudio') as HTMLAudioElement;
  if (!remoteAudio) {
    remoteAudio = document.createElement('audio');
    remoteAudio.id = 'remoteAudio';
    remoteAudio.autoplay = true;
    document.body.appendChild(remoteAudio);
  }

  session.sessionDescriptionHandler.on('addTrack', () => {
    const stream = new MediaStream();
    session.sessionDescriptionHandler.peerConnection.getReceivers().forEach((receiver: any) => {
      if (receiver.track) stream.addTrack(receiver.track);
    });
    remoteAudio.srcObject = stream;
    remoteAudio.play();
  });
};

const initializeSIP = () => {
  console.log('SIP Initialization started...');
  connectionStatus.value = 'Connecting...';

  if (!asteriskConfig.value?.host || !asteriskConfig.value?.port) {
    console.error('Asterisk config missing:', asteriskConfig.value);
    connectionStatus.value = 'Asterisk env settings missing';
    return;
  }

  const domain = asteriskConfig.value.domain || asteriskConfig.value.host;
  const uri = UserAgent.makeURI(`sip:${user.value.sip_extension}@${domain}`);
  console.log(`SIP URI: sip:${user.value.sip_extension}@${domain}`);

  if (!uri) {
    console.error('Failed to create SIP URI');
    connectionStatus.value = 'Invalid SIP URI';
    return;
  }

  const wssUrl = `wss://${asteriskConfig.value.host}:${asteriskConfig.value.port}/ws`;
  console.log(`Connecting to WSS: ${wssUrl}`);

  const transportOptions = {
    server: wssUrl,
    connectionTimeout: 10,
    keepAliveInterval: 30,
    traceSip: true // Enable SIP tracing in console
  };

  try {
    userAgent = new UserAgent({
      uri: uri,
      transportOptions: transportOptions,
      authorizationUsername: user.value.sip_extension,
      authorizationPassword: user.value.sip_password,
      displayName: user.value.name,
      logLevel: 'debug'
    });

    userAgent.start()
      .then(() => {
        console.log('UserAgent started successfully');

        if (userAgent) {
          userAgent.delegate = {
            onConnect: () => {
              console.log('SIP Transport Connected (onConnect)');
              connectionStatus.value = 'Registering...';
            },
            onDisconnect: (error) => {
              console.warn('SIP Transport Disconnected:', error);
              connectionStatus.value = 'Disconnected';
            },
            onInvite: (invitation: Invitation) => {
              console.log('Incoming INVITE:', invitation);
              currentSession = invitation;
              phoneNumber.value = invitation.remoteIdentity.uri.user || 'Unknown';
              callDirection.value = 'inbound';
              isCallActive.value = true;
              connectionStatus.value = 'Ringing...';

              invitation.stateChange.addListener((state: SessionState) => {
                console.log('Invitation state change:', state);
                if (state === SessionState.Established) {
                  connectionStatus.value = 'In Call';
                  isCallActive.value = true;
                  startDurationTimer();
                  setupRemoteAudio(invitation);
                } else if (state === SessionState.Terminated) {
                  endSessionCleanup();
                }
              });
            }
          };
        }

        // Explicitly listen to transport events if reachable
        // @ts-ignore
        userAgent.transport.onConnect = () => console.log('Transport level connected');
        // @ts-ignore
        userAgent.transport.onDisconnect = (err) => console.error('Transport level error:', err);

        const registerer = new Registerer(userAgent!);

        registerer.stateChange.addListener((newState: RegistererState) => {
          console.log('Registerer status changed:', newState);
          if (newState === RegistererState.Registered) {
            connectionStatus.value = 'Connected';
          } else if (newState === RegistererState.Unregistered) {
            connectionStatus.value = 'Registration Failed';
          } else if (newState === RegistererState.Terminated) {
            connectionStatus.value = 'Disconnected';
          }
        });

        console.log('Sending REGISTER request...');
        registerer.register().catch(err => {
          console.error('Registerer.register() catch:', err);
          connectionStatus.value = 'Registration Error';
        });
      })
      .catch((error: Error) => {
        console.error('UserAgent.start() failed:', error);
        connectionStatus.value = 'WSS Error';
        if (error.message.includes('SSL') || error.message.includes('security')) {
          alert('WSS Connection failed. Please ensure you have accepted the certificate at https://' + asteriskConfig.value.host + ':8089/ws');
        }
      });
  } catch (e) {
    console.error('UserAgent creation failed:', e);
    connectionStatus.value = 'Init Error';
  }
};

const makeCall = async () => {
  if (!phoneNumber.value) return;

  connectionStatus.value = 'Originating...';

  try {
    const response = await axios.post('/calls/originate', {
      phone: phoneNumber.value
    });
    console.log('AMI Originate Response:', response.data);
    // The backend successfully told Asterisk to ring our softphone.
    // We just wait for the 'onInvite' event now.
  } catch (error: any) {
    console.error('Failed to originate call via AMI:', error);
    connectionStatus.value = 'Origination Failed';
    alert(error.response?.data?.message || 'Failed to start call');
  }
};

const answerCall = () => {
  if (currentSession && 'accept' in currentSession) {
    (currentSession as Invitation).accept({
      sessionDescriptionHandlerOptions: {
        constraints: { audio: true, video: false }
      }
    });
  }
};

const endCall = () => {
  if (currentSession) {
    if (currentSession.state === SessionState.Established) {
      currentSession.bye();
    } else if (currentSession.state === SessionState.Initial && 'cancel' in currentSession) {
      (currentSession as Inviter).cancel();
    } else if ('reject' in currentSession) {
      (currentSession as Invitation).reject();
    }
  }
  endSessionCleanup();
};

const endSessionCleanup = () => {
  if (callDirection.value && phoneNumber.value) {
    axios.post('/calls', {
      phone: phoneNumber.value,
      duration: durationSeconds,
      direction: callDirection.value
    }).then(() => {
      fetchRecentCalls();
    }).catch(e => console.error('Failed to log call', e));
  }

  currentSession = null;
  isCallActive.value = false;
  callDirection.value = null;
  connectionStatus.value = 'Connected';
  stopDurationTimer();
  callDuration.value = '00:00';
};

const toggleMute = () => {
  if (currentSession && currentSession.sessionDescriptionHandler) {
    // @ts-ignore
    const peerConnection = currentSession.sessionDescriptionHandler.peerConnection;
    peerConnection.getSenders().forEach((sender: any) => {
      if (sender.track && sender.track.kind === 'audio') {
        sender.track.enabled = isMuted.value;
      }
    });
    isMuted.value = !isMuted.value;
  }
};

const fetchRecentCalls = async () => {
  isLoadingHistory.value = true;
  try {
    const response = await axios.get('/calls');
    recentCalls.value = response.data;
  } catch (error) {
    console.error('Failed to load recent calls', error);
  } finally {
    isLoadingHistory.value = false;
  }
};

onUnmounted(() => {
  stopDurationTimer();
  if (userAgent) {
    userAgent.stop();
  }
});

onMounted(() => {
  fetchRecentCalls();

  // Expose the global call method so it can be called from client profiles
  if (typeof window !== 'undefined') {
    // @ts-ignore
    window.initiateAsteriskCall = (phone: string) => {
      dialpadOpen.value = true;
      phoneNumber.value = phone;
      if (!userAgent) {
        initializeSIP();
      }
    };
  }
});
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-6 right-6 z-[9999] flex flex-col items-end">
      <!-- Floating Action Button -->
      <Button v-if="!dialpadOpen" @click="toggleDialpad" class="rounded-full shadow-xl h-16 w-16 bg-blue-600 hover:bg-blue-700 text-white transition-all transform hover:scale-110 hover:shadow-blue-500/40 outline-none ring-4 ring-blue-500/20" style="bottom: 1.5rem; right: 1.5rem;">
        <Phone class="h-7 w-7" />
      </Button>

      <!-- Dialer Widget -->
      <div v-if="dialpadOpen" class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 w-80 overflow-hidden transform transition-all duration-300" :class="isCallActive ? 'scale-100 opacity-100' : 'scale-100 opacity-100'">
        <!-- Header -->
        <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 flex justify-between items-center border-b border-gray-100 dark:border-gray-800">
          <div class="flex items-center space-x-2">
            <div class="h-2 w-2 rounded-full" :class="connectionStatus === 'Connected' ? 'bg-green-500' : (connectionStatus === 'Disconnected' ? 'bg-red-500' : 'bg-yellow-500 animate-pulse')"></div>
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ connectionStatus }}</span>
            <button v-if="connectionStatus === 'Disconnected'" @click="initializeSIP" class="ml-2 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-white bg-blue-500 hover:bg-blue-600 rounded shadow-sm transition-colors uppercase">
              Connect
            </button>
          </div>
          <button @click="closeDialpad" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
            <X class="h-5 w-5" />
          </button>
        </div>

        <!-- Tabs -->
        <div v-if="!isCallActive" class="flex border-b border-gray-100 dark:border-gray-800">
          <button @click="activeTab = 'keypad'" class="flex-1 py-3 text-sm font-medium transition-colors" :class="activeTab === 'keypad' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50 dark:bg-blue-900/20' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800'">Keypad</button>
          <button @click="activeTab = 'recent'" class="flex-1 py-3 text-sm font-medium transition-colors" :class="activeTab === 'recent' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50 dark:bg-blue-900/20' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800'">Recent</button>
        </div>

        <!-- Display -->
        <div class="p-6 text-center">
          <div v-if="isCallActive" class="mb-2 text-sm font-medium text-blue-600 dark:text-blue-400">
            {{ callDuration }}
          </div>
          <Input v-show="activeTab === 'keypad' || isCallActive" v-model="phoneNumber" type="text" placeholder="Enter number..." class="text-2xl text-center font-semibold border-none shadow-none focus-visible:ring-0 px-0 h-12" :disabled="isCallActive" />
        </div>

        <!-- Keypad Tab -->
        <div v-if="!isCallActive && activeTab === 'keypad'" class="px-6 pb-6">
          <div class="grid grid-cols-3 gap-3">
            <button v-for="num in ['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#']" :key="num" @click="appendNumber(num)" class="h-14 w-full rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 text-xl font-medium text-gray-700 dark:text-gray-200 transition-colors flex items-center justify-center">
              {{ num }}
            </button>
            <div class="col-start-2">
              <button @click="appendNumber('+')" class="h-14 w-full rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 text-xl font-medium text-gray-700 dark:text-gray-200 transition-colors flex items-center justify-center">
                +
              </button>
            </div>
          </div>
          <div class="mt-6 flex justify-between items-center px-4">
            <button @click="removeNumber" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-2" title="Backspace">
              <Delete class="h-7 w-7" />
            </button>

            <Button @click="makeCall" :disabled="!phoneNumber || connectionStatus !== 'Connected'" class="rounded-full h-16 w-16 bg-green-500 hover:bg-green-600 text-white shadow-md transition-transform active:scale-95 flex items-center justify-center p-0" :class="{ 'opacity-50 bg-gray-400 hover:bg-gray-400': !phoneNumber || connectionStatus !== 'Connected' }">
              <Phone class="h-7 w-7 fill-current" />
            </Button>

            <button @click="clearNumber" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-2" title="Clear">
              <X class="h-7 w-7" />
            </button>
          </div>
        </div>

        <!-- Recent Tab -->
        <div v-if="!isCallActive && activeTab === 'recent'" class="h-64 overflow-y-auto">
          <div v-if="isLoadingHistory" class="text-center text-sm text-gray-400 py-8">
            Loading recent calls...
          </div>
          <div v-else-if="recentCalls.length === 0" class="text-center text-sm text-gray-400 py-8">
            No recent calls found.
          </div>
          <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
            <li v-for="call in recentCalls" :key="call.id" class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex justify-between items-center transition-colors">
              <div class="flex items-center space-x-3">
                <div class="rounded-full p-2" :class="call.properties.direction === 'inbound' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30' : 'bg-green-50 text-green-600 dark:bg-green-900/30'">
                  <!-- Using Phone icon as base since we don't have inbound/outbound lucide-icons imported yet -->
                  <Phone class="w-4 h-4" />
                </div>
                <div>
                  <div class="font-medium text-sm text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    {{ call.properties.phone }}
                  </div>
                  <div class="text-xs text-gray-500 font-mono mt-0.5">
                    {{ new Date(call.created_at).toLocaleDateString() }} • {{ formatDuration(call.properties.duration) }}
                  </div>
                </div>
              </div>
              <button @click="() => { phoneNumber = call.properties.phone; activeTab = 'keypad'; }" class="text-gray-400 hover:text-green-500 transition-colors p-2" title="Redial">
                <Phone class="w-4 h-4" />
              </button>
            </li>
          </ul>
        </div>

        <div v-if="isCallActive" class="px-6 pb-8 pt-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800">
          <div class="flex justify-center space-x-6">
            <button @click="toggleMute" class="h-14 w-14 rounded-full flex items-center justify-center transition-colors shadow-sm" :class="isMuted ? 'bg-gray-200 dark:bg-gray-700 text-gray-500' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
              <MicOff v-if="isMuted" class="h-6 w-6" />
              <Mic v-else class="h-6 w-6" />
            </button>

            <button v-if="connectionStatus === 'Ringing...' && currentSession && 'accept' in currentSession" @click="answerCall" class="h-14 w-14 rounded-full bg-green-500 hover:bg-green-600 text-white flex items-center justify-center shadow-lg transition-transform active:scale-95">
              <Phone class="h-6 w-6 fill-current" />
            </button>

            <button @click="endCall" class="h-14 w-14 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center shadow-lg transition-transform active:scale-95 translate-y-2">
              <PhoneOff class="h-6 w-6" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
