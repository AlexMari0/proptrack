import { ref } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo?: Echo<any>
  }
}

window.Pusher = Pusher

const echoInstance = ref<any>(null)

export function useEcho() {
  function getEcho(): any {
    if (echoInstance.value) {
      return echoInstance.value
    }

    const token = localStorage.getItem('token')
    if (!token) {
      return null
    }

    const host = (import.meta.env.VITE_REVERB_HOST as string) || '127.0.0.1'
    const port = (import.meta.env.VITE_REVERB_PORT as string) || '8080'
    const scheme = (import.meta.env.VITE_REVERB_SCHEME as string) || 'http'
    const key = (import.meta.env.VITE_REVERB_APP_KEY as string) || ''
    const apiBaseUrl = (import.meta.env.VITE_API_URL as string) || 'http://localhost:8000'

    const echo = new Echo({
      broadcaster: 'reverb',
      key: key,
      wsHost: host,
      wsPort: parseInt(port, 10),
      wssPort: parseInt(port, 10),
      forceTLS: scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: `${apiBaseUrl}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
        },
      },
    })

    echoInstance.value = echo
    window.Echo = echo
    return echo
  }

  function disconnect(): void {
    if (echoInstance.value) {
      echoInstance.value.disconnect()
      echoInstance.value = null
      delete window.Echo
    }
  }

  return {
    getEcho,
    disconnect,
  }
}
